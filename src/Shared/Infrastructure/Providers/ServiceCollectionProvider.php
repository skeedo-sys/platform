<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Providers;

use Application;
use Psr\Container\ContainerInterface;
use Shared\Infrastructure\Collections\ServiceCollection;
use Shared\Infrastructure\Collections\ServiceCollectionInterface;
use Shared\Infrastructure\ServiceProviderInterface;

class ServiceCollectionProvider implements ServiceProviderInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {}

    public function register(Application $app): void
    {
        $collection = new ServiceCollection($this->container);
        $app->set(ServiceCollectionInterface::class, $collection);
    }
}
