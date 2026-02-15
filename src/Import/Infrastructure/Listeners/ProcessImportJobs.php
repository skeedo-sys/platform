<?php

declare(strict_types=1);

namespace Import\Infrastructure\Listeners;

use Ai\Domain\Entities\ConversationEntity;
use Ai\Domain\Repositories\LibraryItemRepositoryInterface;
use Ai\Domain\ValueObjects\ExternalId;
use Ai\Domain\ValueObjects\Meta;
use Cron\Domain\Events\CronEvent;
use Doctrine\ORM\EntityManagerInterface;
use Import\Domain\Entities\ImportJobEntity;
use Import\Domain\Repositories\ImportJobRepositoryInterface;
use Import\Domain\Services\MigrationAdapterInterface;
use Import\Domain\ValueObjects\ImportJobStatus;
use Import\Domain\ValueObjects\ImportSource;
use Shared\Domain\ValueObjects\MaxResults;
use Shared\Infrastructure\Collections\ServiceCollectionInterface;
use Shared\Infrastructure\FileSystem\FileSystemInterface;
use Throwable;

/**
 * Cron listener that processes pending import jobs.
 *
 * This listener picks up pending import jobs, parses the uploaded files
 * using the appropriate migration adapter, and creates conversation entities.
 */
class ProcessImportJobs
{
    private const BATCH_SIZE = 10; // Number of conversations per batch
    private const MAX_JOBS_PER_RUN = 5; // Max jobs to process per cron run

    public function __construct(
        private ImportJobRepositoryInterface $jobRepo,
        private LibraryItemRepositoryInterface $libraryRepo,
        private ServiceCollectionInterface $serviceCollection,
        private EntityManagerInterface $em,
        private FileSystemInterface $fs
    ) {}

    public function __invoke(CronEvent $event): void
    {
        // Get pending jobs
        $jobs = $this->jobRepo
            ->filterByStatus(ImportJobStatus::PENDING)
            ->setMaxResults(new MaxResults(self::MAX_JOBS_PER_RUN));

        foreach ($jobs as $job) {
            $this->processJob($job);
        }

        // Also continue processing jobs that are already in progress
        $processingJobs = $this->jobRepo
            ->filterByStatus(ImportJobStatus::PROCESSING)
            ->setMaxResults(new MaxResults(self::MAX_JOBS_PER_RUN));

        foreach ($processingJobs as $job) {
            $this->processJob($job);
        }
    }

    private function processJob(ImportJobEntity $job): void
    {
        try {
            // Mark as processing
            if ($job->getStatus() === ImportJobStatus::PENDING) {
                $job->setStatus(ImportJobStatus::PROCESSING);
                $this->em->flush();
            }

            // Find the appropriate adapter
            $adapter = $this->findAdapter($job->getSource());
            if ($adapter === null) {
                $job->setStatus(ImportJobStatus::FAILED);
                $job->setErrorMessage('No migration adapter found for source: ' . $job->getSource()->value);
                $this->em->flush();
                return;
            }

            // Check if file exists locally
            $file = $job->getFile();
            $localFilePath = $file->getObjectKey()->value;

            if (!$this->fs->fileExists($localFilePath)) {
                $job->setStatus(ImportJobStatus::FAILED);
                $job->setErrorMessage('Import file not found: ' . $localFilePath);
                $this->em->flush();
                return;
            }

            // Process conversations
            $processedCount = 0;
            foreach ($adapter->parse($file, $job->getWorkspace(), $job->getUser()) as $conversation) {
                // Skip already processed conversations in this batch
                if ($processedCount < $job->getProcessedCount()) {
                    $processedCount++;
                    continue;
                }

                $this->importConversation($job, $conversation);
                $processedCount++;

                // Limit batch size
                if ($processedCount - $job->getProcessedCount() >= self::BATCH_SIZE) {
                    $this->em->flush();
                    return; // Continue in next cron run
                }
            }

            // All done
            $job->setStatus(ImportJobStatus::COMPLETED);

            // Clean up the uploaded file
            try {
                $this->fs->delete($localFilePath);
            } catch (\Throwable) {
                // Ignore errors during cleanup
            }

            $this->em->flush();
        } catch (Throwable $e) {
            $job->setStatus(ImportJobStatus::FAILED);
            $job->setErrorMessage($e->getMessage());
            try {
                $file = $job->getFile();
                $localFilePath = $file->getObjectKey()->value;
                if ($this->fs->fileExists($localFilePath)) {
                    $this->fs->delete($localFilePath);
                }
            } catch (\Throwable) {
                // Ignore cleanup errors
            }
            $this->em->flush();
        }
    }

    private function findAdapter(ImportSource $source): ?MigrationAdapterInterface
    {
        $adapters = $this->serviceCollection->ofType(MigrationAdapterInterface::class);

        foreach ($adapters as $key => $adapter) {
            if ($key === $source->value) {
                return $adapter;
            }
        }

        return null;
    }

    private function importConversation(
        ImportJobEntity $job,
        ConversationEntity $conversation
    ): void {
        // Extract original ID from metadata (set by adapter)
        $meta = $conversation->getMeta()->data ?? [];
        $originalId = $meta['_original_id'] ?? null;

        if ($originalId === null) {
            // If no original ID, skip this conversation
            $job->incrementSkippedCount();
            $job->incrementProcessedCount();
            return;
        }

        $externalId = $job->getSource()->value . ':' . $originalId;

        // Check for duplicate by external ID
        try {
            $this->libraryRepo->ofExternalId(new ExternalId($externalId));
            // If we get here, it already exists
            $job->incrementSkippedCount();
            $job->incrementProcessedCount();
            return;
        } catch (Throwable) {
            // No existing conversation found, proceed with import
        }

        try {
            // Set external ID for duplicate detection
            $conversation->setExternalId(new ExternalId($externalId));

            // Update metadata with import information
            $meta['migration'] = [
                'provider' => $job->getSource()->value,
                'imported_at' => time(),
                'original_id' => $originalId,
            ];
            // Remove temporary _original_id
            unset($meta['_original_id']);
            $conversation->setMeta(new Meta($meta));

            // Persist the conversation (messages are already added via cascade)
            $this->em->persist($conversation);

            $job->incrementProcessedCount();
        } catch (Throwable $e) {
            $job->incrementFailedCount();
            $job->incrementProcessedCount();
        }
    }
}
