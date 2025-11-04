<?php

declare(strict_types=1);

namespace Ai\Domain\Speech;

use Ai\Domain\Services\AiServiceInterface;
use Psr\Http\Message\StreamInterface;
use User\Domain\Entities\UserEntity;
use Voice\Domain\Entities\VoiceEntity;
use Voice\Domain\ValueObjects\Gender;
use Voice\Domain\ValueObjects\LanguageCode;

interface VoiceCloningServiceInterface extends AiServiceInterface
{
    public function cloneVoice(
        string $name,
        StreamInterface $file,
        UserEntity $user,
        ?LanguageCode $locale = null,
        ?Gender $gender = null,
    ): VoiceEntity;

    public function deleteVoice(string $id): void;
}
