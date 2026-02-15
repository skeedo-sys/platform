<?php

declare(strict_types=1);

namespace Ai\Domain\Embedding;

use Ai\Domain\Entities\ConversationEntity;
use Ai\Domain\ValueObjects\Embedding;
use Assistant\Domain\Entities\AssistantEntity;
use Shared\Domain\ValueObjects\Id;

interface VectorStoreInterface
{
    public function isEnabled(): bool;
    public function getName(): string;

    /**
     * Upsert a vector into the vector store
     * 
     * @param Id $id The ID of the vector
     * @param Embedding $embedding The vector to upsert
     * @param AssistantEntity|ConversationEntity|null $context The context of the vector
     */
    public function upsert(
        Id $id,
        Embedding $embedding,
        AssistantEntity|ConversationEntity|null $context = null
    ): void;

    /**
     * Check if a vector exists in the vector store
     * 
     * @param Id $id The ID of the vector
     * @param AssistantEntity|ConversationEntity|null $context The context of the vector
     * @return bool True if the vector exists, false otherwise
     */
    public function exists(
        Id $id,
        AssistantEntity|ConversationEntity|null $context = null
    ): bool;

    /**
     * Retrieve a vector from the vector store
     * 
     * @param Id $id The ID of the vector
     * @param AssistantEntity|ConversationEntity|null $context The context of the vector
     * @return Embedding The vector
     */
    public function retrieve(
        Id $id,
        AssistantEntity|ConversationEntity|null $context = null
    ): Embedding;

    /**
     * Remove a vector from the vector store
     * 
     * @param Id $id The ID of the vector
     * @param AssistantEntity|ConversationEntity|null $context The context of the vector
     */
    public function remove(
        Id $id,
        AssistantEntity|ConversationEntity|null $context = null
    ): void;

    /**
     * Search for similar vectors within a context
     * 
     * @param array<float> $vector The search vector
     * @param AssistantEntity|ConversationEntity $context The search scope
     * @param int $limit Maximum number of results
     * @return array<array{content: string, similarity: float}> Search results
     */
    public function search(
        array $vector,
        AssistantEntity|ConversationEntity $context,
        int $limit = 5
    ): array;
}
