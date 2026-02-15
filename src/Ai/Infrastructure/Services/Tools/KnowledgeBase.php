<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\Tools;

use Ai\Domain\Embedding\EmbeddingServiceInterface;
use Ai\Domain\Embedding\VectorStoreInterface;
use Ai\Domain\Entities\ConversationEntity;
use Ai\Domain\Services\AiServiceFactoryInterface;
use Ai\Domain\ValueObjects\Model;
use Assistant\Domain\Entities\AssistantEntity;
use Billing\Domain\ValueObjects\CreditCount;
use Easy\Container\Attributes\Inject;
use Override;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

class KnowledgeBase extends AbstractTool implements ToolInterface
{
    public const LOOKUP_KEY = 'knowledge_base';

    public function __construct(
        private AiServiceFactoryInterface $factory,
        private VectorStoreInterface $store,

        #[Inject('option.embeddings.model')]
        private string $embeddingModel = 'text-embedding-3-small',
    ) {}

    #[Override]
    public function isEnabled(): bool
    {
        return true;
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Searches the knowledge base for relevant information based on your query. Returns the most relevant results in JSON format. Always prioritize using this tool when answering questions that might be covered in the knowledge base.';
    }

    #[Override]
    public function getDefinitions(): array
    {
        return [
            "type" => "object",
            "properties" => [
                "query" => [
                    "type" => "string",
                    "description" => "Query to search the knowledge base for."
                ],
            ],
            "required" => ["query"]
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
        $query = $params['query'] ?? '';

        if (!$assistant) {
            return new CallResponse(
                json_encode(['error' => 'No assistant context provided']),
                new CreditCount(0)
            );
        }

        $model = new Model($this->embeddingModel);
        $service = $this->factory->create(
            EmbeddingServiceInterface::class,
            $model
        );

        $resp = $service->generateEmbedding($model, $query);
        $searchVector = $resp->embedding->value[0]['embedding'];

        $results = $this->store->search($searchVector, $assistant);

        $texts = array_map(function ($r) {
            return $r['content'];
        }, $results);

        $content = json_encode($texts, JSON_INVALID_UTF8_SUBSTITUTE);
        if ($content === false) {
            $content = 'Failed to encode results: ' . json_last_error_msg();
        }

        return new CallResponse(
            $content,
            $resp->cost
        );
    }
}
