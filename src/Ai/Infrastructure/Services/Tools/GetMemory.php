<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\Tools;

use Ai\Domain\Entities\MemoryEntity;
use Ai\Domain\Repositories\LibraryItemRepositoryInterface;
use Ai\Domain\ValueObjects\ItemType;
use Ai\Domain\ValueObjects\SortParameter;
use Billing\Domain\ValueObjects\CreditCount;
use Easy\Container\Attributes\Inject;
use Override;
use Shared\Domain\ValueObjects\MaxResults;
use Shared\Domain\ValueObjects\SortDirection;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

class GetMemory extends AbstractTool implements ToolInterface
{
    public const LOOKUP_KEY = 'get_memory';

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
        return 'Retrieves saved memories about the user and workspace. Returns personal information, preferences, and facts previously shared by the user.';
    }

    #[Override]
    public function getSystemInstructions(): ?string
    {
        return 'Use the ' . self::LOOKUP_KEY . ' tool when you need to know personal information about the user to answer their question (e.g., their name, preferences, background, or facts they\'ve previously shared). Only call this when the answer requires knowing something specific about the user.';
    }

    #[Override]
    public function getDefinitions(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "limit" => [
                    "type" => "integer",
                    "description" => "The number of memories to retrieve. Minimum 1, maximum 50. Defaults to 10 if not specified."
                ]
            ],
            "required" => []
        ];
    }

    #[Override]
    public function call(
        UserEntity $user,
        WorkspaceEntity $workspace,
        array $params = [],
        array $files = [],
        array $knowledgeBase = [],
    ): CallResponse {
        $limit = $params['limit'] ?? 10;

        $entities = $this->repo
            ->filterByWorkspace($workspace)
            ->filterByUser($user, $workspace)
            ->filterByType(ItemType::MEMORY)
            ->sort(SortDirection::DESC, SortParameter::CREATED_AT)
            ->setMaxResults(new MaxResults($limit));

        $memory = [];
        /** @var MemoryEntity */
        foreach ($entities as $entity) {
            $owner = $entity->getUser();
            $memory[] = [
                'id' => $entity->getId()->getValue()->toString(),
                'content' => $entity->getContent(),
                'visibility' => $entity->getVisibility()->name,
                'created_at' => $entity->getCreatedAt()->getTimestamp(),
                'owner' => [
                    'id' => $owner->getId()->getValue()->toString(),
                    'first_name' => $owner->getFirstName(),
                    'last_name' => $owner->getLastName(),
                    'email' => $owner->getEmail(),
                ],
            ];
        }

        $data = [
            'user' => [
                'id' => $user->getId()->getValue()->toString(),
                'first_name' => $user->getFirstName(),
                'last_name' => $user->getLastName(),
                'email' => $user->getEmail(),
            ],
            'memories' => $memory,
        ];

        $content = "Workspace memories are listed below. Pick the most relevant memory to answer the user's question. <memory>: " . json_encode($data, JSON_PRETTY_PRINT) . "</memory>";

        return new CallResponse(
            $content,
            new CreditCount(0)
        );
    }
}
