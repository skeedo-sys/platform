<?php

declare(strict_types=1);

namespace Ai\Domain\Embedding;

use Ai\Domain\ValueObjects\Embedding;
use Shared\Domain\ValueObjects\Id;

interface VectorStoreInterface
{
    public function store(Id $id, Embedding $embedding): void;
    public function has(Id $id): bool;
    public function get(Id $id): Embedding;
    public function delete(Id $id): void;
}
