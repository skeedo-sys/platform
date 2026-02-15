<?php

declare(strict_types=1);

namespace Import\Domain\ValueObjects;

use JsonSerializable;
use Override;

/**
 * Enum representing the status of an import job.
 */
enum ImportJobStatus: string implements JsonSerializable
{
    /** Job is waiting to be processed */
    case PENDING = 'pending';

    /** Job is currently being processed */
    case PROCESSING = 'processing';

    /** Job completed successfully */
    case COMPLETED = 'completed';

    /** Job failed with an error */
    case FAILED = 'failed';

    #[Override]
    public function jsonSerialize(): string
    {
        return $this->value;
    }
}

