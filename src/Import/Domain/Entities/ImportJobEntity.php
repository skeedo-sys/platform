<?php

declare(strict_types=1);

namespace Import\Domain\Entities;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use File\Domain\Entities\FileEntity;
use Import\Domain\ValueObjects\ImportJobStatus;
use Import\Domain\ValueObjects\ImportSource;
use Shared\Domain\ValueObjects\Id;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

/**
 * Entity representing an import job for migrating conversations
 * from external sources like ChatGPT, Claude, or Grok.
 */
#[ORM\Entity]
#[ORM\Table(name: 'import_job')]
#[ORM\HasLifecycleCallbacks]
class ImportJobEntity
{
    /** A unique identifier of the entity. */
    #[ORM\Embedded(class: Id::class, columnPrefix: false)]
    private Id $id;

    #[ORM\ManyToOne(targetEntity: WorkspaceEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private WorkspaceEntity $workspace;

    #[ORM\ManyToOne(targetEntity: UserEntity::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private UserEntity $user;

    #[ORM\Embedded(class: ImportSource::class, columnPrefix: false)]
    private ImportSource $source;

    #[ORM\Column(type: Types::STRING, enumType: ImportJobStatus::class, name: 'status')]
    private ImportJobStatus $status;

    /** The uploaded archive file */
    #[ORM\ManyToOne(targetEntity: FileEntity::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private FileEntity $file;

    /** Number of conversations processed so far */
    #[ORM\Column(type: Types::INTEGER, name: 'processed_count', options: ['default' => 0])]
    private int $processedCount = 0;

    /** Total number of conversations to process */
    #[ORM\Column(type: Types::INTEGER, name: 'total_count', nullable: true)]
    private ?int $totalCount = null;

    /** Number of conversations skipped (duplicates) */
    #[ORM\Column(type: Types::INTEGER, name: 'skipped_count', options: ['default' => 0])]
    private int $skippedCount = 0;

    /** Number of conversations that failed to import */
    #[ORM\Column(type: Types::INTEGER, name: 'failed_count', options: ['default' => 0])]
    private int $failedCount = 0;

    /** Error message if job failed */
    #[ORM\Column(type: Types::TEXT, name: 'error_message', nullable: true)]
    private ?string $errorMessage = null;

    /** Creation date and time of the entity */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, name: 'created_at')]
    private DateTimeInterface $createdAt;

    /** The date and time when the entity was last modified. */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'updated_at', nullable: true)]
    private ?DateTimeInterface $updatedAt = null;

    /** The date and time when processing started */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'started_at', nullable: true)]
    private ?DateTimeInterface $startedAt = null;

    /** The date and time when processing completed */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, name: 'completed_at', nullable: true)]
    private ?DateTimeInterface $completedAt = null;

    public function __construct(
        WorkspaceEntity $workspace,
        UserEntity $user,
        ImportSource $source,
        FileEntity $file
    ) {
        $this->id = new Id();
        $this->workspace = $workspace;
        $this->user = $user;
        $this->source = $source;
        $this->file = $file;
        $this->status = ImportJobStatus::PENDING;
        $this->createdAt = new DateTimeImmutable();
    }

    public function getId(): Id
    {
        return $this->id;
    }

    public function getWorkspace(): WorkspaceEntity
    {
        return $this->workspace;
    }

    public function getUser(): UserEntity
    {
        return $this->user;
    }

    public function getSource(): ImportSource
    {
        return $this->source;
    }

    public function getStatus(): ImportJobStatus
    {
        return $this->status;
    }

    public function setStatus(ImportJobStatus $status): self
    {
        $this->status = $status;

        if ($status === ImportJobStatus::PROCESSING && $this->startedAt === null) {
            $this->startedAt = new DateTime();
        }

        if (
            $status === ImportJobStatus::COMPLETED
            || $status === ImportJobStatus::FAILED
        ) {
            $this->completedAt = new DateTime();
        }

        return $this;
    }

    public function getFile(): FileEntity
    {
        return $this->file;
    }

    public function getProcessedCount(): int
    {
        return $this->processedCount;
    }

    public function incrementProcessedCount(): self
    {
        $this->processedCount++;
        return $this;
    }

    public function getTotalCount(): ?int
    {
        return $this->totalCount;
    }

    public function setTotalCount(int $totalCount): self
    {
        $this->totalCount = $totalCount;
        return $this;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function incrementSkippedCount(): self
    {
        $this->skippedCount++;
        return $this;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    public function incrementFailedCount(): self
    {
        $this->failedCount++;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function getStartedAt(): ?DateTimeInterface
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?DateTimeInterface
    {
        return $this->completedAt;
    }

    /**
     * Get the progress as a percentage (0-100)
     */
    public function getProgressPercentage(): ?float
    {
        if ($this->totalCount === null || $this->totalCount === 0) {
            return null;
        }

        return round(($this->processedCount / $this->totalCount) * 100, 2);
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new DateTime();
    }
}

