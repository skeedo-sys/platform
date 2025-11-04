<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\OpenAi;

use Ai\Domain\Entities\VideoEntity;
use Ai\Domain\Exceptions\DomainException;
use Ai\Domain\Exceptions\InsufficientCreditsException;
use Ai\Domain\Exceptions\ModelNotSupportedException;
use Ai\Domain\ValueObjects\ExternalId;
use Ai\Domain\ValueObjects\Model;
use Ai\Domain\ValueObjects\RequestParams;
use Ai\Domain\Video\VideoServiceInterface;
use Ai\Infrastructure\Services\AbstractBaseService;
use Ai\Infrastructure\Services\CostCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Easy\Container\Attributes\Inject;
use Override;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileInterface;
use Shared\Infrastructure\FileSystem\CdnInterface;
use Shared\Infrastructure\Services\ModelRegistry;
use Throwable;
use User\Domain\Entities\UserEntity;
use Workspace\Domain\Entities\WorkspaceEntity;

class VideoService extends AbstractBaseService implements VideoServiceInterface
{
    public function __construct(
        private Client $client,
        private CostCalculator $calc,
        private CdnInterface $cdn,
        private ModelRegistry $registry,
        private EntityManagerInterface $em,
        private StreamFactoryInterface $streamFactory,

        #[Inject('option.features.is_safety_enabled')]
        private bool $checkSafety = true,

        #[Inject('option.features.video.is_enabled')]
        private bool $isToolEnabled = false,
    ) {
        parent::__construct($registry, 'openai', 'video');
    }

    #[Override]
    public function generateVideo(
        WorkspaceEntity $workspace,
        UserEntity $user,
        Model $model,
        ?array $params = null
    ): VideoEntity {
        if (!$this->supportsModel($model)) {
            throw new ModelNotSupportedException(
                self::class,
                $model
            );
        }

        if (!$params || !array_key_exists('prompt', $params)) {
            throw new DomainException('Missing parameter: prompt');
        }

        $estimate = $this->calc->estimate($model);
        if (!$workspace->hasSufficientCredit($estimate)) {
            throw new InsufficientCreditsException();
        }

        $workspace->allocate($estimate);
        $this->em->flush(); // Save the workspace with the allocated credits

        $endpoint = '/v1/videos';
        $data = [
            'prompt' => $params['prompt'],
            'model' => $model->value
        ];
        $headers = [];

        if (array_key_exists('aspect_ratio', $params)) {
            $data['size'] = $params['aspect_ratio'];
        }

        if (array_key_exists('duration', $params)) {
            $data['seconds'] = $params['duration'];
        }

        if (isset($params['frames'])) {
            /** @var UploadedFileInterface $frame */
            $frame = $params['frames'][0];

            $stream = $frame->getStream();
            $stream->rewind();
            $contents = $stream->getContents();

            $filename = $frame->getClientFilename();
            $extension = pathinfo($filename, PATHINFO_EXTENSION);

            $key = $this->cdn->generatePath($extension, $workspace, $user);
            $this->cdn->write($key, $contents);

            $data['input_reference'] = $this->streamFactory
                ->createStreamFromResource($this->cdn->readStream($key));
            $headers['Content-Type'] = 'multipart/form-data';
        }

        try {
            $resp = $this->client->sendRequest('POST', $endpoint, $data, headers: $headers);
        } catch (Throwable $th) {
            $workspace->unallocate($estimate);
            throw $th;
        }

        $entity = new VideoEntity(
            $workspace,
            $user,
            $model,
            RequestParams::fromArray($params)
        );

        $content = $resp->getBody()->getContents();
        $content = json_decode($content);

        $externalId = "openai/" . $content->id;
        $entity->setExternalId(new ExternalId($externalId));

        $entity->addMeta('reserved_credit', $estimate->value);

        return $entity;
    }
}
