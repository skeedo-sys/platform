<?php

declare(strict_types=1);

namespace Assistant\Application\CommandHandlers;

use Assistant\Application\Commands\CountAssistantsCommand;
use Assistant\Domain\Repositories\AssistantRepositoryInterface;

class CountAssistantsCommandHandler
{
    public function __construct(
        private AssistantRepositoryInterface $repo
    ) {}

    public function handle(CountAssistantsCommand $cmd): int
    {
        $assistants = $this->repo;

        if ($cmd->scope) {
            $assistants = $assistants->filterByAccessScope(
                $cmd->scope,
                $cmd->user,
                $cmd->workspace
            );
        } else {
            if ($cmd->workspace) {
                $assistants = $assistants->filterByWorkspace($cmd->workspace);
            }

            if ($cmd->user) {
                $assistants = $assistants->filterByUser($cmd->user);
            }
        }

        if ($cmd->ids) {
            $assistants = $assistants->filterById($cmd->ids);
        }

        if ($cmd->query) {
            $assistants = $assistants->search($cmd->query);
        }

        if ($cmd->status) {
            $assistants = $assistants->filterByStatus($cmd->status);
        }

        return $assistants->count();
    }
}
