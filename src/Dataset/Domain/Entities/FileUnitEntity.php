<?php

declare(strict_types=1);

namespace Dataset\Domain\Entities;

use Doctrine\ORM\Mapping as ORM;
use File\Domain\Entities\FileEntity;

#[ORM\Entity]
class FileUnitEntity extends AbstractDataUnitEntity
{
    #[ORM\ManyToOne(targetEntity: FileEntity::class, cascade: ['persist'])]
    private FileEntity $file;

    public function __construct(
        FileEntity $file
    ) {
        parent::__construct();
        $this->file = $file;
    }

    public function getFile(): FileEntity
    {
        return $this->file;
    }
}
