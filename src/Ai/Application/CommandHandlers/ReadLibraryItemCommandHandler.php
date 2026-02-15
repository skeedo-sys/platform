<?php

declare(strict_types=1);

namespace Ai\Application\CommandHandlers;

use Ai\Application\Commands\ReadLibraryItemCommand;
use Ai\Domain\Entities\AbstractLibraryItemEntity;
use Ai\Domain\Exceptions\LibraryItemNotFoundException;
use Ai\Domain\Repositories\LibraryItemRepositoryInterface;
use Ai\Domain\ValueObjects\ExternalId;

class ReadLibraryItemCommandHandler
{
    public function __construct(
        private LibraryItemRepositoryInterface $repo,
    ) {}

    /**
     * @throws LibraryItemNotFoundException
     */
    public function handle(ReadLibraryItemCommand $cmd): AbstractLibraryItemEntity
    {
        if ($cmd->item instanceof ExternalId) {
            return $this->repo->ofExternalId($cmd->item);
        }

        return $this->repo->ofId($cmd->item);
    }
}
