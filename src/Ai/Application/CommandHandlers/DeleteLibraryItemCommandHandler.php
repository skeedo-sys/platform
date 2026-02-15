<?php

declare(strict_types=1);

namespace Ai\Application\CommandHandlers;

use Ai\Application\Commands\DeleteLibraryItemCommand;
use Ai\Domain\Embedding\VectorStoreInterface;
use Ai\Domain\Entities\AbstractLibraryItemEntity;
use Ai\Domain\Entities\ConversationEntity;
use Ai\Domain\Exceptions\LibraryItemNotFoundException;
use Ai\Domain\Repositories\LibraryItemRepositoryInterface;
use File\Domain\Entities\FileEntity;
use Shared\Infrastructure\FileSystem\CdnInterface;
use Throwable;

class DeleteLibraryItemCommandHandler
{
    public function __construct(
        private LibraryItemRepositoryInterface $repo,
        private VectorStoreInterface $store,
        private CdnInterface $cdn,
    ) {}

    /**
     * @throws LibraryItemNotFoundException
     */
    public function handle(DeleteLibraryItemCommand $cmd): void
    {
        $item = $cmd->item instanceof AbstractLibraryItemEntity
            ? $cmd->item
            : $this->repo->ofId($cmd->item);

        $this->repo->remove($item);

        // Get context for vector store - ConversationEntity for chat files
        $context = $item instanceof ConversationEntity ? $item : null;

        foreach ($item->getFiles() as $file) {
            try {
                $this->cdn->delete($file->getObjectKey()->value);

                if ($file instanceof FileEntity && $file->isVectorized()) {
                    $this->store->remove($file->getId(), $context);
                }
            } catch (Throwable $e) {
                // Unable to delete file from CDN, this is not a critical error
                // and we can safely ignore it
            }
        }
    }
}
