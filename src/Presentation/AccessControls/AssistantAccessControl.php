<?php

declare(strict_types=1);

namespace Presentation\AccessControls;

use Ai\Domain\ValueObjects\Visibility;
use Assistant\Application\Commands\ReadAssistantCommand;
use Assistant\Domain\Entities\AssistantEntity;
use Assistant\Domain\Exceptions\AssistantNotFoundException;
use Easy\Http\Message\StatusCode;
use Presentation\Exceptions\HttpException;
use Presentation\Exceptions\NotFoundException;
use Shared\Infrastructure\CommandBus\Dispatcher;
use User\Domain\Entities\UserEntity;

class AssistantAccessControl
{
    public function __construct(
        private Dispatcher $dispatcher
    ) {}

    public function isGranted(
        Permission $permission,
        UserEntity $user,
        string|AssistantEntity $assistant
    ): bool {
        if (is_string($assistant)) {
            $assistant = $this->getItem($assistant);
        }

        $isGranted = match ($permission) {
            Permission::ASSISTANT_DELETE => $this->canDelete($user, $assistant),
            Permission::ASSISTANT_EDIT => $this->canEdit($user, $assistant),
            Permission::ASSISTANT_USE => $this->canUse($user, $assistant),
            default =>  false
        };

        return $isGranted;
    }

    public function denyUnlessGranted(
        Permission $permission,
        UserEntity $user,
        string|AssistantEntity $assistant
    ): void {
        if (!$this->isGranted($permission, $user, $assistant)) {
            throw new HttpException(statusCode: StatusCode::FORBIDDEN);
        }
    }

    private function canDelete(
        UserEntity $user,
        AssistantEntity $assistant
    ): bool {
        $ws = $assistant->getWorkspace();
        $owner = $assistant->getUser();

        if (!$owner || !$ws) {
            // This is either a system assistant or belongs to someone else.
            return false;
        }

        if ($owner->getId()->equals($user->getId())) {
            // Assistant owners can delete their assistants.
            return true;
        }

        if ($ws->getOwner()->getId()->equals($user->getId())) {
            // Workspace admins can delete any assistant.
            return true;
        }

        return false;
    }

    private function canEdit(
        UserEntity $user,
        AssistantEntity $assistant
    ): bool {
        $ws = $assistant->getWorkspace();
        $owner = $assistant->getUser();

        if (!$owner || !$ws) {
            // This is either a system assistant or belongs to someone else.
            return false;
        }

        if ($owner->getId()->equals($user->getId())) {
            // Assistant owners can edit their assistants.
            return true;
        }

        return false;
    }

    private function canUse(
        UserEntity $user,
        AssistantEntity $assistant
    ): bool {
        $ws = $assistant->getWorkspace();
        $owner = $assistant->getUser();
        $visibility = $assistant->getVisibility();

        if ($visibility === Visibility::PUBLIC) {
            // Public assistants can be used by anyone.
            return true;
        }

        if ($visibility === Visibility::WORKSPACE) {
            if (!$ws) {
                // Unexpected case, return false to be safe.
                return false;
            }

            if ($ws->getOwner()->getId()->equals($user->getId())) {
                // Workspace admin can use assistants shared in the workspace.
                return true;
            }

            foreach ($ws->getUsers() as $member) {
                if ($member->getId()->equals($user->getId())) {
                    // Workspace members can use assistants shared in the workspace.
                    return true;
                }
            }

            return false;
        }

        if ($visibility === Visibility::PRIVATE) {
            if ($owner && $owner->getId()->equals($user->getId())) {
                // Only the owner can use private assistants.
                return true;
            }

            return false;
        }

        return false;
    }

    private function getItem(string $id): AssistantEntity
    {
        try {
            $cmd = new ReadAssistantCommand($id);

            /** @var AssistantEntity */
            $item = $this->dispatcher->dispatch($cmd);
        } catch (AssistantNotFoundException $th) {
            throw new NotFoundException(previous: $th);
        }

        return $item;
    }
}
