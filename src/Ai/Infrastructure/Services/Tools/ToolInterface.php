<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\Tools;

use Ai\Domain\Entities\ConversationEntity;
use Assistant\Domain\Entities\AssistantEntity;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

interface ToolInterface
{
    public function isEnabled(): bool;
    public function getDescription(): string;
    public function getDefinitions(): array;

    /**
     * @param ConversationEntity $conversation The conversation context
     * @param WorkspaceEntity $workspace The workspace context
     * @param UserEntity $user The user context
     * @param AssistantEntity|null $assistant The assistant for knowledge base search
     * @param array|null $params The parameters for the tool
     */
    public function call(
        ConversationEntity $conversation,
        WorkspaceEntity $workspace,
        UserEntity $user,
        ?AssistantEntity $assistant = null,
        ?array $params = null,
    ): CallResponse;

    /**
     * @return string|null
     */
    public function getSystemInstructions(): ?string;
}
