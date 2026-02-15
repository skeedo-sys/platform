<?php

declare(strict_types=1);

namespace Import\Domain\Services;

use Ai\Domain\Entities\ConversationEntity;
use File\Domain\Entities\FileEntity;
use Generator;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

/**
 * Interface for conversation migration adapters.
 *
 * Implementations of this interface are responsible for parsing
 * conversation export files from different AI providers (ChatGPT, Claude, Grok)
 * and converting them into ConversationEntity objects.
 */
interface MigrationAdapterInterface
{
    /**
     * Get a human-readable name for this adapter.
     */
    public function getName(): string;

    /**
     * Get a description of what this adapter imports.
     */
    public function getDescription(): string;

    /**
     * Check if this adapter can handle the given file.
     *
     * @param FileEntity $file The uploaded ZIP file
     * @return bool True if this adapter can parse the file
     */
    public function supports(FileEntity $file): bool;

    /**
     * Parse the export file and yield conversation entities.
     *
     * @param FileEntity $file The uploaded ZIP file
     * @param WorkspaceEntity $workspace The workspace to import into
     * @param UserEntity $user The user performing the import
     * @return Generator<ConversationEntity> Yields ConversationEntity objects with messages
     */
    public function parse(
        FileEntity $file,
        WorkspaceEntity $workspace,
        UserEntity $user
    ): Generator;

    /**
     * Count the total number of conversations in the file.
     *
     * @param FileEntity $file The uploaded ZIP file
     * @return int Total number of conversations
     */
    public function count(FileEntity $file): int;
}
