<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Collections;

use IteratorAggregate;
use Psr\Container\ContainerInterface;
use Traversable;

/**
 * Generic collection implementation for service implementations.
 * 
 * @template T of object
 * @implements ServiceCollectionInterface<T>
 */
class ServiceCollection implements ServiceCollectionInterface
{
    /** @var array<string, array<string, class-string<T>|T>> */
    private array $services = [];

    /**
     * @param ContainerInterface $container
     */
    public function __construct(
        private ContainerInterface $container,
    ) {}

    /**
     * Add a service implementation to the collection.
     * 
     * @param string $key The key to register the implementation under.
     * @param class-string<T>|T $service The service to register.
     * @param class-string $type The interface/class type
     * @return static
     */
    public function add(string $key, string|object $service, string $type): static
    {
        if (!isset($this->services[$type])) {
            $this->services[$type] = [];
        }

        $this->services[$type][$key] = $service;
        return $this;
    }

    /**
     * Get a service implementation from the collection.
     * 
     * @param string $key The key to retrieve the implementation for.
     * @param class-string $type The interface/class type
     * @return T
     * @throws ServiceNotFoundException
     */
    public function get(string $key, string $type): object
    {
        if (!isset($this->services[$type][$key])) {
            throw new ServiceNotFoundException($key, $type);
        }

        $service = $this->services[$type][$key];

        if (is_object($service)) {
            return $service;
        }

        $service = $this->resolveService($service, $type);
        $this->services[$type][$key] = $service;

        return $service;
    }

    /**
     * Get an iterator for services of a specific type.
     * 
     * @param class-string $type The interface/class type
     * @return IteratorAggregate<string, T>
     */
    public function ofType(string $type): IteratorAggregate
    {
        return new class($this, $type) implements IteratorAggregate
        {
            public function __construct(
                private ServiceCollection $collection,
                private string $type,
            ) {}

            public function getIterator(): Traversable
            {
                $keys = $this->collection->getKeysForType($this->type);
                foreach ($keys as $key) {
                    yield $key => $this->collection->get($key, $this->type);
                }
            }
        };
    }

    /**
     * Get all keys for a specific type.
     * 
     * @param class-string $type The interface/class type
     * @return array<string>
     */
    public function getKeysForType(string $type): array
    {
        if (!isset($this->services[$type])) {
            return [];
        }

        return array_keys($this->services[$type]);
    }

    /**
     * @param class-string<T> $serviceClass
     * @param class-string $type
     * @return T
     */
    private function resolveService(string $serviceClass, string $type): object
    {
        $service = $this->container->get($serviceClass);

        if (!is_object($service) || !($service instanceof $type)) {
            throw new \RuntimeException(
                "Service `{$serviceClass}` is not an instance of {$type}"
            );
        }

        return $service;
    }
}
