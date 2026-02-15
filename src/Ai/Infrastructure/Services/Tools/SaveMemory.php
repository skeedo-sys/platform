<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\Tools;

use Ai\Domain\Entities\ConversationEntity;
use Ai\Domain\Entities\MemoryEntity;
use Ai\Domain\Repositories\LibraryItemRepositoryInterface;
use Ai\Domain\ValueObjects\Content;
use Ai\Domain\ValueObjects\Visibility;
use Assistant\Domain\Entities\AssistantEntity;
use Billing\Domain\ValueObjects\CreditCount;
use Easy\Container\Attributes\Inject;
use Override;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

class SaveMemory extends AbstractTool implements ToolInterface
{
    public const LOOKUP_KEY = 'save_memory';

    public function __construct(
        private LibraryItemRepositoryInterface $repo,

        #[Inject('option.features.tools.memory.is_enabled')]
        private ?bool $isEnabled = null,
    ) {}

    #[Override]
    public function isEnabled(): bool
    {
        return (bool) $this->isEnabled;
    }

    #[Override]
    public function getDescription(): string
    {
        return 'AUTOMATICALLY saves important LONG-TERM personal information about the user to memory. Call this tool when the user shares lasting facts about their identity, preferences, interests, job, location, or ongoing projects - information that will remain relevant across multiple conversations. DO NOT save temporary information, short-term plans, or task-specific context that is only relevant to the current conversation. Save each distinct fact separately.';
    }

    #[Override]
    public function getSystemInstructions(): ?string
    {
        return 'MEMORY SYSTEM: When the user shares personal information (their name, job, preferences, location, interests, projects, or any facts about themselves), you MUST AUTOMATICALLY call the ' . self::LOOKUP_KEY . ' tool to save it for future reference - even if they don\'t explicitly ask you to remember it. Make separate tool calls for each distinct piece of information.';
    }

    #[Override]
    public function getDefinitions(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "content" => [
                    "type" => "string",
                    "description" => "The information to save to memory."
                ]
            ],
            "required" => ["content"]
        ];
    }

    #[Override]
    public function call(
        ConversationEntity $conversation,
        WorkspaceEntity $workspace,
        UserEntity $user,
        ?AssistantEntity $assistant = null,
        ?array $params = null,
    ): CallResponse {
        $content = $params['content'] ?? '';

        // Create and save memory entity
        $memory = new MemoryEntity(
            $workspace,
            $user,
            new Content($content),
            new CreditCount(0),
            Visibility::PRIVATE
        );

        $this->repo->add($memory);

        return new CallResponse(
            json_encode([
                'success' => true,
                'memory_id' => (string) $memory->getId()->getValue(),
                'message' => 'Information saved to memory successfully.'
            ], JSON_PRETTY_PRINT),
            new CreditCount(0)
        );
    }
}
