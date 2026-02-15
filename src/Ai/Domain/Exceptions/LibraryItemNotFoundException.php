<?php

declare(strict_types=1);

namespace Ai\Domain\Exceptions;

use Ai\Domain\ValueObjects\ExternalId;
use Exception;
use Shared\Domain\ValueObjects\Id;
use Throwable;

class LibraryItemNotFoundException extends Exception
{
    public function __construct(
        public readonly Id|ExternalId $id,
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        $message = $id instanceof Id
            ? sprintf(
                "Library item with id <%s> doesn't exists!",
                $id->getValue()
            )
            : sprintf(
                "Library item with external id <%s> doesn't exists!",
                $id->value
            );

        parent::__construct(
            $message,
            $code,
            $previous
        );
    }
}
