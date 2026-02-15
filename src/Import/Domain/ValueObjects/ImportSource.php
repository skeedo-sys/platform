<?php

declare(strict_types=1);

namespace Import\Domain\ValueObjects;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use JsonSerializable;
use Override;
use Shared\Domain\Exceptions\InvalidValueException;

#[ORM\Embeddable]
class ImportSource implements JsonSerializable
{
    #[ORM\Column(type: Types::STRING, name: "source", nullable: true)]
    public readonly string $value;

    public function __construct(string $value)
    {
        $this->ensureValueIsValid($value);
        $this->value = $value;
    }

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }

    /**
     * @throws InvalidValueException
     */
    private function ensureValueIsValid(string $value)
    {
        if (mb_strlen($value) > 50) {
            throw new InvalidValueException(sprintf(
                '<%s> does not allow the value <%s>. Maximum <%s> characters allowed.',
                static::class,
                $value,
                50
            ));
        }
    }
}

