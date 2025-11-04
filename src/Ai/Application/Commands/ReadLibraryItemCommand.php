<?php

declare(strict_types=1);

namespace Ai\Application\Commands;

use Ai\Application\CommandHandlers\ReadLibraryItemCommandHandler;
use Ai\Domain\ValueObjects\ExternalId;
use Shared\Domain\ValueObjects\Id;
use Shared\Infrastructure\CommandBus\Attributes\Handler;

#[Handler(ReadLibraryItemCommandHandler::class)]
class ReadLibraryItemCommand
{
    public Id|ExternalId $item;

    public function __construct(string|Id|ExternalId $item)
    {
        $this->item = is_string($item) ? new Id($item) : $item;
    }
}
