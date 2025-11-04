<?php

declare(strict_types=1);

namespace File\Domain\Entities;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class FileEntity extends AbstractFileEntity
{
    /** Creation date and time of the entity */
    #[ORM\Column(type: Types::BOOLEAN, name: 'is_vectorized')]
    private ?bool $isVectorized = null;

    public function isVectorized(): bool
    {
        return $this->isVectorized === true;
    }

    public function markAsVectorized(): void
    {
        $this->isVectorized = true;
    }
}
