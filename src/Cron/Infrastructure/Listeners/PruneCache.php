<?php

declare(strict_types=1);

namespace Cron\Infrastructure\Listeners;

use Cron\Domain\Events\CronEvent;
use Easy\Container\Attributes\Inject;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\Cache\PruneableInterface;

class PruneCache
{
    public function __construct(
        private CacheItemPoolInterface $cacheItemPool,

        #[Inject('config.enable_caching')]
        private bool $enableCaching = false,
    ) {}

    public function __invoke(CronEvent $event): void
    {
        if (!$this->enableCaching) {
            return;
        }

        if ($this->cacheItemPool instanceof PruneableInterface) {
            $this->cacheItemPool->prune();
        }
    }
}
