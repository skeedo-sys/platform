<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Collections;

use IteratorAggregate;

/**
 * @template T of object
 */
interface ServiceCollectionInterface
{
    /**
     * @param string $key Lookup key
     * @param class-string<T>|T $service The service class string (any class that implements $type) or instance of T
     * @param class-string<T> $type The interface/class type
     * @return static
     */
    public function add(
        string $key,
        string|object $service,
        string $type
    ): static;

    /**
     * @param string $key Lookup key
     * @param class-string<T> $type The interface/class type
     * @return T
     * @throws ServiceNotFoundException
     */
    public function get(string $key, string $type): object;

    /**
     * @param class-string<T> $type The interface/class type
     * @return IteratorAggregate<string, T>
     */
    public function ofType(string $type): IteratorAggregate;
}
