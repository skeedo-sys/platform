<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\Tools;

use Override;

abstract class AbstractTool implements ToolInterface
{
    #[Override]
    public function getSystemInstructions(): ?string
    {
        return null;
    }
}
