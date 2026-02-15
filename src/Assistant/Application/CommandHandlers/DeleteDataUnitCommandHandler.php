<?php

declare(strict_types=1);

namespace Assistant\Application\CommandHandlers;

use Ai\Domain\Embedding\VectorStoreInterface;
use Assistant\Application\Commands\DeleteDataUnitCommand;
use Assistant\Domain\Entities\AssistantEntity;
use Assistant\Domain\Exceptions\AssistantNotFoundException;
use Assistant\Domain\Repositories\AssistantRepositoryInterface;
use Dataset\Domain\Entities\AbstractDataUnitEntity;

class DeleteDataUnitCommandHandler
{
    public function __construct(
        private AssistantRepositoryInterface $repo,
        private VectorStoreInterface $store,
    ) {}

    /**
     * @param DeleteDataUnitCommand $cmd
     * @return AssistantEntity
     * @throws AssistantNotFoundException
     */
    public function handle(DeleteDataUnitCommand $cmd): AssistantEntity
    {
        $assistant = $cmd->assistant instanceof AssistantEntity
            ? $cmd->assistant
            : $this->repo->ofId($cmd->assistant);

        $assistant->removeDataUnit($cmd->unit);

        $id = $cmd->unit instanceof AbstractDataUnitEntity
            ? $cmd->unit->getId()
            : $cmd->unit;

        $this->store->remove($id, $assistant);

        return $assistant;
    }
}
