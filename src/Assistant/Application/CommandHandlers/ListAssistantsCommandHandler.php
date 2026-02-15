<?php

declare(strict_types=1);

namespace Assistant\Application\CommandHandlers;

use Assistant\Application\Commands\ListAssistantsCommand;
use Assistant\Domain\Entities\AssistantEntity;
use Assistant\Domain\Exceptions\AssistantNotFoundException;
use Assistant\Domain\Repositories\AssistantRepositoryInterface;
use Shared\Domain\ValueObjects\CursorDirection;
use Traversable;

class ListAssistantsCommandHandler
{
    public function __construct(
        private AssistantRepositoryInterface $repo
    ) {}

    /**
     * @return Traversable<AssistantEntity>
     * @throws AssistantNotFoundException
     */
    public function handle(ListAssistantsCommand $cmd): Traversable
    {
        $cursor = $cmd->cursor
            ? $this->repo->ofId($cmd->cursor)
            : null;

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

        if ($cmd->sortDirection) {
            $assistants = $assistants->sort($cmd->sortDirection, $cmd->sortParameter);
        }

        if ($cmd->status) {
            $assistants = $assistants->filterByStatus($cmd->status);
        }

        if ($cmd->ids) {
            $assistants = $assistants->filterById($cmd->ids);
        }

        if ($cmd->query) {
            $assistants = $assistants->search($cmd->query);
        }

        if ($cmd->maxResults) {
            $assistants = $assistants->setMaxResults($cmd->maxResults);
        }

        if ($cursor) {
            if ($cmd->cursorDirection == CursorDirection::ENDING_BEFORE) {
                return $assistants = $assistants->endingBefore($cursor);
            }

            return $assistants->startingAfter($cursor);
        }

        return $assistants;
    }
}
