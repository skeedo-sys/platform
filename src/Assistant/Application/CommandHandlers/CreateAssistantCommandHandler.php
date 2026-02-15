<?php

declare(strict_types=1);

namespace Assistant\Application\CommandHandlers;

use Assistant\Application\Commands\CreateAssistantCommand;
use Assistant\Domain\Entities\AssistantEntity;
use Assistant\Domain\Repositories\AssistantRepositoryInterface;
use Assistant\Domain\ValueObjects\SortParameter;
use Shared\Domain\ValueObjects\Id;
use Shared\Domain\ValueObjects\MaxResults;
use Shared\Domain\ValueObjects\SortDirection;
use Shared\Infrastructure\CommandBus\Dispatcher;
use User\Application\Commands\ReadUserCommand;
use Workspace\Application\Commands\ReadWorkspaceCommand;

class CreateAssistantCommandHandler
{
    public function __construct(
        private Dispatcher $dispatcher,
        private AssistantRepositoryInterface $repo
    ) {}

    public function handle(CreateAssistantCommand $cmd): AssistantEntity
    {
        $ws = $cmd->workspace;
        $user = $cmd->user;

        if ($ws instanceof Id) {
            /** @var WorkspaceEntity */
            $ws = $this->dispatcher->dispatch(new ReadWorkspaceCommand($ws));
        }

        if ($user instanceof Id) {
            /** @var UserEntity */
            $user = $this->dispatcher->dispatch(new ReadUserCommand($user));
        }

        $assistant = new AssistantEntity($cmd->name);

        if ($cmd->expertise) {
            $assistant->setExpertise($cmd->expertise);
        }

        if ($cmd->description) {
            $assistant->setDescription($cmd->description);
        }

        if ($cmd->instructions) {
            $assistant->setInstructions($cmd->instructions);
        }

        if ($cmd->avatar) {
            $assistant->setAvatar($cmd->avatar);
        }

        if ($cmd->model) {
            $assistant->setModel($cmd->model);
        }

        if ($cmd->status) {
            $assistant->setStatus($cmd->status);
        }

        if ($ws && $user) {
            $assistant->setOwner($ws, $user);
        }

        $assistant->setVisibility($cmd->visibility);

        $first = $this->repo->sort(SortDirection::ASC, SortParameter::POSITION)
            ->setMaxResults(new MaxResults(1))
            ->getIterator()
            ->current();

        if ($first) {
            $assistant->placeBetween(null, $first);
        }

        $this->repo->add($assistant);
        return $assistant;
    }
}
