<?php

declare(strict_types=1);

namespace Import\Domain\Exceptions;

use Exception;
use Shared\Domain\ValueObjects\Id;
use Throwable;

/**
 * Exception thrown when an import job is not found.
 */
class ImportJobNotFoundException extends Exception
{
    public function __construct(
        public readonly Id $id,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct(
            sprintf('Import job with ID "%s" not found', $id->getValue()->toString()),
            $code,
            $previous
        );
    }
}

