<?php

declare(strict_types=1);

namespace Cron\Infrastructure\Listeners;

use Ai\Domain\Embedding\VectorStoreInterface;
use Cron\Domain\Events\CronEvent;
use Dataset\Domain\Entities\LinkUnitEntity;
use Dataset\Domain\Entities\FileUnitEntity;
use Doctrine\ORM\EntityManagerInterface;
use File\Domain\Entities\FileEntity;

/**
 * @deprecated Since version 3.5.0 - This listener is designed for one-time migration 
 * of embeddings from legacy database storage to the new vector store. It will be 
 * removed in v4.0.0 after the migration is complete.
 */
class MigrateEmbeddings
{
    public function __construct(
        private EntityManagerInterface $em,
        private VectorStoreInterface $store,
    ) {}

    public function __invoke(CronEvent $event)
    {
        // Increase memory limit and optimize for this migration
        ini_set('memory_limit', '512M');
        ini_set('max_execution_time', 0); // No time limit

        for ($i = 0; $i < 10; $i++) {
            try {
                $this->migrateLinkDataUnits();
            } catch (\Throwable $th) {
                throw $th;
            }

            $fileDataUnitsProcessed = false;
            try {
                $fileDataUnitsProcessed = $this->migrateFileDataUnits();
            } catch (\Throwable $th) {
                throw $th;
            }

            // Only run migrateFileEntities if no FileUnitEntity records were processed in this iteration
            if (!$fileDataUnitsProcessed) {
                try {
                    $this->migrateFileEntities();
                } catch (\Throwable $th) {
                    throw $th;
                }
            }

            // Optional: Force garbage collection between iterations
            if ($i % 3 === 0) {
                gc_collect_cycles();
            }
        }
    }

    private function migrateLinkDataUnits(): bool
    {
        // Use raw SQL to get just the ID first
        $connection = $this->em->getConnection();
        $sql = 'SELECT id FROM data_unit WHERE discr = ? AND embedding IS NOT NULL AND JSON_LENGTH(embedding) > 0 LIMIT 1';
        $result = $connection->executeQuery($sql, ['link']);
        $idBinary = $result->fetchOne();

        if (!$idBinary) {
            return false; // No records processed
        }

        // Convert binary UUID to string format
        $idString = bin2hex($idBinary);
        $idString = substr($idString, 0, 8) . '-' . substr($idString, 8, 4) . '-' . substr($idString, 12, 4) . '-' . substr($idString, 16, 4) . '-' . substr($idString, 20, 12);

        // Now load the entity by ID
        /** @var LinkUnitEntity $unit */
        $unit = $this->em->find(LinkUnitEntity::class, $idString);
        if (!$unit || !property_exists($unit, 'embedding')) {
            return false;
        }

        $embedding = $unit->embedding;
        $this->store->store($unit->getId(), $embedding);

        // Use SQL to set embedding to NULL instead of loading the entity
        $updateSql = 'UPDATE data_unit SET embedding = NULL WHERE id = ?';
        $connection->executeStatement($updateSql, [$idBinary]);

        $this->em->clear();
        return true; // Record processed
    }

    private function migrateFileDataUnits(): bool
    {
        // Use raw SQL to get just the ID first - check for FileUnitEntity records that have associated files with embeddings
        $connection = $this->em->getConnection();
        $sql = 'SELECT du.id FROM data_unit du 
            LEFT JOIN file f ON du.file_id = f.id 
            WHERE du.discr = ? AND f.embedding IS NOT NULL AND JSON_LENGTH(f.embedding) > 0 LIMIT 1';
        $result = $connection->executeQuery($sql, ['file']);
        $idBinary = $result->fetchOne();

        if (!$idBinary) {
            return false; // No records processed
        }

        // Convert binary UUID to string format
        $idString = bin2hex($idBinary);
        $idString = substr($idString, 0, 8) . '-' . substr($idString, 8, 4) . '-' . substr($idString, 12, 4) . '-' . substr($idString, 16, 4) . '-' . substr($idString, 20, 12);

        // Now load the entity by ID
        /** @var FileUnitEntity $unit */
        $unit = $this->em->find(FileUnitEntity::class, $idString);
        if (!$unit) {
            return false;
        }

        $file = $unit->getFile();
        if (!property_exists($file, 'embedding')) {
            return false;
        }

        $embedding = $file->embedding;
        $this->store->store($unit->getId(), $embedding);

        // Use SQL to set embedding to NULL in the file table
        // Get the file ID as binary for the SQL update
        $fileIdBinary = $file->getId()->getValue()->getBytes();
        $updateSql = 'UPDATE file SET embedding = NULL WHERE id = ?';
        $connection->executeStatement($updateSql, [$fileIdBinary]);

        $this->em->clear();
        return true; // Record processed
    }

    private function migrateFileEntities(): bool
    {
        // Use raw SQL to get just the ID first
        $connection = $this->em->getConnection();
        $sql = 'SELECT id FROM file WHERE embedding IS NOT NULL AND JSON_LENGTH(embedding) > 0 LIMIT 1';
        $result = $connection->executeQuery($sql);
        $idBinary = $result->fetchOne();

        if (!$idBinary) {
            return false; // No records processed
        }

        // Convert binary UUID to string format
        $idString = bin2hex($idBinary);
        $idString = substr($idString, 0, 8) . '-' . substr($idString, 8, 4) . '-' . substr($idString, 12, 4) . '-' . substr($idString, 16, 4) . '-' . substr($idString, 20, 12);

        // Now load the entity by ID
        $entity = $this->em->find(FileEntity::class, $idString);
        if (!$entity || !property_exists($entity, 'embedding')) {
            return false;
        }

        $embedding = $entity->embedding;
        $this->store->store($entity->getId(), $embedding);

        // Use SQL to set embedding to NULL instead of loading the entity
        $updateSql = 'UPDATE file SET embedding = NULL WHERE id = ?';
        $connection->executeStatement($updateSql, [$idBinary]);

        $this->em->clear();
        return true; // Record processed
    }
}
