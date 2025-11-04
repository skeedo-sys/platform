<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\Speechify;

use Ai\Domain\Speech\VoiceCloningServiceInterface;
use Ai\Domain\ValueObjects\Model;
use Override;
use Psr\Http\Message\StreamInterface;
use Traversable;
use User\Domain\Entities\UserEntity;
use Voice\Domain\Entities\VoiceEntity;
use Voice\Domain\ValueObjects\ExternalId;
use Voice\Domain\ValueObjects\Gender;
use Voice\Domain\ValueObjects\LanguageCode;
use Voice\Domain\ValueObjects\Name;
use Voice\Domain\ValueObjects\Provider;
use Voice\Domain\ValueObjects\SampleUrl;

class VoiceCloningService implements VoiceCloningServiceInterface
{
    private array $models = [
        'speechify'
    ];

    public function __construct(
        private Client $client,
    ) {}

    #[Override]
    public function supportsModel(Model $model): bool
    {
        return in_array($model->value, $this->models);
    }

    #[Override]
    public function getSupportedModels(): Traversable
    {
        foreach ($this->models as $model) {
            yield new Model($model);
        }
    }

    #[Override]
    public function cloneVoice(
        string $name,
        StreamInterface $file,
        UserEntity $user,
        ?LanguageCode $locale = null,
        ?Gender $gender = null,
    ): VoiceEntity {
        $body = [
            'name' => $name,
            'sample' => $file,
            'consent' => json_encode([
                'email' => $user->getEmail()->value,
                'fullName' => $user->getFirstName()->value . ' ' . $user->getLastName()->value,
            ]),
        ];

        if ($locale) {
            $body['locale'] = $locale->value;
        }

        if ($gender) {
            $body['gender'] = $gender->value;

            if ($gender === Gender::NEUTRAL) {
                $body['gender'] = 'notSpecified';
            }
        }

        $resp = $this->client->sendRequest(
            'POST',
            '/voices',
            $body,
            headers: [
                'Accept' => '*/*',
                'Content-Type' => 'multipart/form-data'
            ]
        );

        $body = json_decode($resp->getBody()->getContents());

        $voice = new VoiceEntity(
            new Provider('speechify'),
            new Model('simba-multilingual'),
            new Name($body->display_name),
            new ExternalId($body->id),
            new SampleUrl(null),
        );

        return $voice;
    }

    #[Override]
    public function deleteVoice(string $id): void
    {
        $this->client->sendRequest(
            'DELETE',
            '/voices/' . $id,
        );
    }
}
