<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Embedding;

use Ai\Domain\Embedding\VectorStoreInterface;
use Ai\Domain\Services\AiServiceFactoryInterface;
use Ai\Domain\ValueObjects\Embedding;
use Ai\Domain\ValueObjects\EmbeddingMap;
use Easy\Container\Attributes\Inject;
use Override;
use Psr\SimpleCache\CacheInterface;
use Shared\Domain\ValueObjects\Id;
use Shared\Infrastructure\FileSystem\CdnInterface;

class FileVectorStore implements VectorStoreInterface
{
    private const CACHE_PREFIX = 'embedding_';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private AiServiceFactoryInterface $factory,
        private CdnInterface $cdn,
        private CacheInterface $cache,

        #[Inject('config.enable_caching')]
        private bool $enableCaching = false,
    ) {}

    #[Override]
    public function store(Id $id, Embedding $embedding): void
    {
        $path = $this->getPath($id);
        $this->cdn->write($path, json_encode($embedding));

        // Cache the embedding for faster retrieval
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id);
            $this->cache->set($cacheKey, $embedding, self::CACHE_TTL);
        }
    }

    #[Override]
    public function has(Id $id): bool
    {
        // Check cache first if enabled
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id);
            if ($this->cache->has($cacheKey)) {
                return true;
            }
        }

        // Fallback to CDN check
        $path = $this->getPath($id);
        return $this->cdn->has($path);
    }

    #[Override]
    public function get(Id $id): Embedding
    {
        // Try cache first if enabled
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id);
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Fallback to CDN
        if (!$this->has($id)) {
            throw new \Exception('Embedding not found');
        }

        $path = $this->getPath($id);
        $embedding = $this->cdn->read($path);
        $vectors = json_decode($embedding, true);

        $vectors = array_map(function ($vector) {
            return new EmbeddingMap($vector['content'], $vector['embedding']);
        }, $vectors);

        $embedding = new Embedding(...array_values($vectors));

        // Cache the result if caching is enabled
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id);
            $this->cache->set($cacheKey, $embedding, self::CACHE_TTL);
        }

        return $embedding;
    }

    #[Override]
    public function delete(Id $id): void
    {
        $path = $this->getPath($id);

        if ($this->cdn->has($path)) {
            $this->cdn->delete($path);
        }

        // Remove from cache if enabled
        if ($this->enableCaching) {
            $cacheKey = $this->getCacheKey($id);
            $this->cache->delete($cacheKey);
        }
    }

    private function getPath(Id $id): string
    {
        return "/embeddings/" . $id->getValue() . ".json";
    }

    private function getCacheKey(Id $id): string
    {
        return self::CACHE_PREFIX . $id->getValue();
    }
}
