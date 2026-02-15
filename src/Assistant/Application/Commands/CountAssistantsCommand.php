<?php

declare(strict_types=1);

namespace Assistant\Application\Commands;

use Assistant\Application\CommandHandlers\CountAssistantsCommandHandler;
use Assistant\Domain\Scope;
use Assistant\Domain\ValueObjects\Status;
use Shared\Domain\ValueObjects\Id;
use Shared\Infrastructure\CommandBus\Attributes\Handler;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

#[Handler(CountAssistantsCommandHandler::class)]
class CountAssistantsCommand
{
    public ?Scope $scope = Scope::ACCESSIBLE;

    public null|Id|UserEntity $user = null;
    public null|Id|WorkspaceEntity $workspace = null;

    public ?Status $status = null;

    /** @var null|array<Id> */
    public ?array $ids = null;

    /** Search terms/query */
    public ?string $query = null;

    public function setWorkspace(string|Id|WorkspaceEntity $workspace): void
    {
        $this->workspace = is_string($workspace)
            ? new Id($workspace)
            : $workspace;
    }

    public function setStatus(int $status): self
    {
        $this->status = Status::from($status);
        return $this;
    }

    public function setIds(string|Id ...$ids): void
    {
        $this->ids = array_map(
            fn($id) => is_string($id) ? new Id($id) : $id,
            $ids
        );
    }
}
