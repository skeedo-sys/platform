<?php

declare(strict_types=1);

namespace Ai\Infrastructure\Services\OpenAi;

use Ai\Application\Commands\ReadLibraryItemCommand;
use Ai\Domain\Entities\AbstractLibraryItemEntity;
use Ai\Domain\Entities\VideoEntity;
use Ai\Domain\ValueObjects\ExternalId;
use Ai\Domain\ValueObjects\State;
use Ai\Infrastructure\Services\CostCalculator;
use Billing\Domain\Events\CreditUsageEvent;
use Billing\Domain\ValueObjects\CreditCount;
use File\Domain\Entities\FileEntity;
use File\Domain\ValueObjects\ObjectKey;
use File\Domain\ValueObjects\Size;
use File\Domain\ValueObjects\Storage;
use File\Domain\ValueObjects\Url;
use Easy\Container\Attributes\Inject;
use File\Domain\Entities\ImageFileEntity;
use File\Domain\ValueObjects\Height;
use File\Domain\ValueObjects\Width;
use File\Infrastructure\BlurhashGenerator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Shared\Infrastructure\CommandBus\Dispatcher;
use Shared\Infrastructure\FileSystem\CdnInterface;
use stdClass;

class VideoWebhookProcessor
{
    public function __construct(
        private Client $client,
        private CdnInterface $cdn,
        private CostCalculator $calc,
        private Dispatcher $dispatcher,
        private EventDispatcherInterface $ed,

        #[Inject('option.billing.negative_balance_enabled')]
        private bool $negativeBalance = false,
    ) {}

    public function __invoke(stdClass $payload): void
    {
        $type = $payload->type;
        $data = $payload->data;
        $externalId = $data->id;

        // Find library item by id
        $cmd = new ReadLibraryItemCommand(
            new ExternalId("openai/" . $externalId)
        );

        /** @var AbstractLibraryItemEntity $entity */
        $entity = $this->dispatcher->dispatch($cmd);

        if (!($entity instanceof VideoEntity)) {
            // Not a video, unexpected
            return;
        }

        $user = $entity->getUser();
        $ws = $entity->getWorkspace();

        if (in_array($entity->getState(), [State::COMPLETED, State::FAILED])) {
            // Video already completed or failed
            return;
        }

        // Update status
        match ($type) {
            'video.completed' => $entity->setState(State::COMPLETED),
            'video.failed' => $entity->setState(State::FAILED),
        };

        $cost = null;

        // Get video details
        $resp = $this->client->sendRequest('GET', '/v1/videos/' . $externalId);
        $content = $resp->getBody()->getContents();
        $details = json_decode($content);

        if ($entity->getState() == State::COMPLETED) {
            // Retrieve video content
            $resp = $this->client->sendRequest('GET', '/v1/videos/' . $externalId . '/content');
            $content = $resp->getBody()->getContents();

            $ext = 'mp4';
            $key = $this->cdn->generatePath($ext, $ws, $user);
            $this->cdn->write($key, $content);

            $file = new FileEntity(
                new Storage($this->cdn->getAdapterLookupKey()),
                new ObjectKey($key),
                new Url($this->cdn->getUrl($key)),
                new Size(strlen($content)),
            );

            $entity->setOutputFile($file);

            // Retrieve cover image
            $resp = $this->client->sendRequest(
                'GET',
                '/v1/videos/' . $externalId . '/content',
                params: [
                    'variant' => 'thumbnail'
                ]
            );
            $content = $resp->getBody()->getContents();
            $img = imagecreatefromstring($content);
            $width = imagesx($img);
            $height = imagesy($img);

            $key = $this->cdn->generatePath('jpg', $ws, $user);
            $this->cdn->write($key, $content);

            $imgFile = new ImageFileEntity(
                new Storage($this->cdn->getAdapterLookupKey()),
                new ObjectKey($key),
                new Url($this->cdn->getUrl($key)),
                new Size(strlen($content)),
                new Width($width),
                new Height($height),
                BlurhashGenerator::generateBlurHash($img, $width, $height),
            );

            $entity->setCoverImage($imgFile);

            // Calculate cost
            $opt = null;
            if (isset($details->size)) {
                $size = $details->size;
                if (in_array($size, ['720x1280', '1280x720'])) {
                    $opt |= CostCalculator::QUALITY_SD;
                } else if (in_array($size, ['1024x1792', '1792x1024'])) {
                    $opt |= CostCalculator::QUALITY_HD;
                }
            }

            $count = (int)($details->seconds ?? 4);
            $cost = $this->calc->calculate(
                $count,
                $entity->getModel(),
                $opt
            );

            $entity->addCost($cost);
        }

        if ($entity->getState() == State::FAILED) {
            $entity->addMeta(
                'failure_reason',
                $details->error->message ?? $details->error->code ?? 'Unknown error'
            );
        }

        // Unallocate reserved credit
        $amount = (float) ($entity->getMeta('reserved_credit') ?: 0);
        $reserved = new CreditCount($amount);
        $ws->unallocate($reserved);

        if ($cost) {
            // Deduct credit from workspace
            $ws->deductCredit($cost, $this->negativeBalance);

            // Dispatch event
            $event = new CreditUsageEvent($ws, $cost);
            $this->ed->dispatch($event);
        }
    }
}
