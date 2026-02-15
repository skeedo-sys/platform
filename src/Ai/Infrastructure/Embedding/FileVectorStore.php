<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Embedding;

use Ai\Domain\Embedding\VectorStoreInterface;
use Ai\Domain\Entities\ConversationEntity;
use Ai\Domain\ValueObjects\Embedding;
use Ai\Domain\ValueObjects\EmbeddingMap;
use Ai\Infrastructure\Services\VectorSearch;
use Assistant\Domain\Entities\AssistantEntity;
use Easy\Container\Attributes\Inject;
use League\Flysystem\StorageAttributes;
use Override;
use Psr\SimpleCache\CacheInterface;
use Shared\Domain\ValueObjects\Id;
use Shared\Infrastructure\FileSystem\CdnInterface;

class FileVectorStore implements VectorStoreInterface
{
    public const LOOKUP_KEY = 'file_vector_store';

    private const CACHE_PREFIX = 'embedding_';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private CdnInterface $cdn,
        private CacheInterface $cache,
        private VectorSearch $vectorSearch,

        #[Inject('config.enable_caching')]
        private bool $enableCaching = false,
    ) {}

    #[Override]
    public function isEnabled(): bool
    {
        // File vector store is always enabled
        return true;
    }

    #[Override]
    public function getName(): string
    {
        return 'File System (JSON)';
    }

    #[Override]
    public function upsert(
        Id $id,
        Embedding $embedding,
        AssistantEntity|ConversationEntity|null $context = null
    ): void {
        $namespace = $this->getNamespace($context);
        $path = $this->getPath($id, $namespace);

        $dir = dirname($path);
        if (!$this->cdn->directoryExists($dir)) {
            $this->cdn->createDirectory($dir);
        }

        $this->cdn->write($path, json_encode($embedding));

        // Cache the embedding for faster retrieval
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id, $namespace);
            $this->cache->set($cacheKey, $embedding, self::CACHE_TTL);
        }
    }

    #[Override]
    public function exists(
        Id $id,
        AssistantEntity|ConversationEntity|null $context = null
    ): bool {
        $namespace = $this->getNamespace($context);

        // Check cache first if enabled
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id, $namespace);
            if ($this->cache->has($cacheKey)) {
                return true;
            }
        }

        // Fallback to CDN check
        $path = $this->getPath($id, $namespace);
        return $this->cdn->fileExists($path);
    }

    #[Override]
    public function retrieve(
        Id $id,
        AssistantEntity|ConversationEntity|null $context = null
    ): Embedding {
        $namespace = $this->getNamespace($context);

        // Try cache first if enabled
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id, $namespace);
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Fallback to CDN
        if (!$this->exists($id, $context)) {
            throw new \Exception('Embedding not found');
        }

        $path = $this->getPath($id, $namespace);
        $content = $this->cdn->read($path);
        $vectors = json_decode($content, true);

        $vectors = array_map(function ($vector) {
            return new EmbeddingMap($vector['content'], $vector['embedding']);
        }, $vectors);

        $embedding = new Embedding(...array_values($vectors));

        // Cache the result if caching is enabled
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id, $namespace);
            $this->cache->set($cacheKey, $embedding, self::CACHE_TTL);
        }

        return $embedding;
    }

    #[Override]
    public function remove(
        Id $id,
        AssistantEntity|ConversationEntity|null $context = null
    ): void {
        $namespace = $this->getNamespace($context);
        $path = $this->getPath($id, $namespace);

        if ($this->cdn->fileExists($path)) {
            $this->cdn->delete($path);
        }

        // Remove from cache if enabled
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id, $namespace);
            $this->cache->delete($cacheKey);
        }
    }

    #[Override]
    public function search(
        array $vector,
        AssistantEntity|ConversationEntity $context,
        int $limit = 5
    ): array {
        $embeddings = [];

        if ($context instanceof AssistantEntity) {
            // Load all embeddings from assistant namespace
            $namespace = 'assistant_' . $context->getId()->getValue();
            $dir = '/embeddings/' . $namespace;

            if ($this->cdn->directoryExists($dir)) {
                $files = $this->cdn->listContents($dir)->toArray();

                foreach ($files as $file) {
                    /** @var StorageAttributes $file */
                    if ($file->isFile()) {
                        try {
                            $content = $this->cdn->read($file->path());
                            $vectors = json_decode($content, true);
                            if ($vectors) {
                                $embeddings[] = $vectors;
                            }
                        } catch (\Throwable $th) {
                            continue;
                        }
                    }
                }
            }
        } elseif ($context instanceof ConversationEntity) {
            // Load embeddings for conversation files from workspace namespace
            $workspace = $context->getWorkspace();
            $namespace = 'workspace_' . $workspace->getId()->getValue();

            foreach ($context->getFiles() as $file) {
                try {
                    $path = $this->getPath($file->getId(), $namespace);
                    if ($this->cdn->fileExists($path)) {
                        $content = $this->cdn->read($path);
                        $vectors = json_decode($content, true);
                        if ($vectors) {
                            $embeddings[] = $vectors;
                        }
                    }
                } catch (\Throwable $th) {
                    continue;
                }
            }
        }

        return $this->vectorSearch->searchVectors($vector, $embeddings, $limit);
    }

    private function getNamespace(AssistantEntity|ConversationEntity|null $context): string
    {
        return match (true) {
            $context instanceof AssistantEntity => 'assistant_' . $context->getId()->getValue(),
            $context instanceof ConversationEntity => 'workspace_' . $context->getWorkspace()->getId()->getValue(),
            default => 'default'
        };
    }

    private function getPath(Id $id, string $namespace): string
    {
        return '/embeddings/' . $namespace . '/' . $id->getValue() . '.json';
    }

    private function getCacheKey(Id $id, string $namespace): string
    {
        return self::CACHE_PREFIX . $namespace . '_' . $id->getValue();
    }
}
