<?php

declare(strict_types=1);

namespace Billing\Domain\ValueObjects\PlanConfig;

use JsonSerializable;
use Override;

class ChatConfig implements JsonSerializable
{
    /**
     * @param bool $isEnabled Whether or not chat is enabled
     * @param null|int $cap The maximum number of custom assistants
     * @return void
     */
    public function __construct(
        public readonly bool $isEnabled,
        public readonly ?int $cap = null,
    ) {}

    #[Override]
    public function jsonSerialize(): array
    {
        return [
            'is_enabled' => $this->isEnabled,
            'custom_assistants_cap' => $this->cap,
        ];
    }
}
