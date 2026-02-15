<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Collections;

class ServiceNotFoundException extends \RuntimeException
{
    public function __construct(
        string $key,
        string $interfaceClass,
    ) {
        parent::__construct(
            "Service with key '{$key}' not found in collection for {$interfaceClass}"
        );
    }
}
