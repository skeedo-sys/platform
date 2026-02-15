<?php

declare(strict_types=1);

namespace Assistant\Application\CommandHandlers;

use Ai\Domain\Embedding\EmbeddingServiceInterface;
use Ai\Domain\Embedding\VectorStoreInterface;
use Ai\Domain\Services\AiServiceFactoryInterface;
use Ai\Domain\ValueObjects\Embedding;
use Ai\Domain\ValueObjects\Model;
use Ai\Infrastructure\Services\DocumentReader\DocumentReader;
use Assistant\Application\Commands\CreateDataUnitCommand;
use Assistant\Domain\Entities\AssistantEntity;
use Assistant\Domain\Repositories\AssistantRepositoryInterface;
use Billing\Domain\Events\CreditUsageEvent;
use Billing\Domain\ValueObjects\CreditCount;
use File\Domain\Entities\FileEntity;
use File\Domain\ValueObjects\ObjectKey;
use File\Domain\ValueObjects\Size;
use File\Domain\ValueObjects\Storage;
use File\Domain\ValueObjects\Url;
use Psr\Http\Message\UploadedFileInterface;
use Ramsey\Uuid\Nonstandard\Uuid;
use Dataset\Domain\Entities\AbstractDataUnitEntity;
use Dataset\Domain\Entities\FileUnitEntity;
use Dataset\Domain\Entities\LinkUnitEntity;
use Dataset\Domain\ValueObjects\Title;
use Dataset\Domain\ValueObjects\Url as DatasetUrl;
use Easy\Container\Attributes\Inject;
use RuntimeException;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\FilesystemException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Client\ClientExceptionInterface;
use Shared\Infrastructure\FileSystem\CdnInterface;
use Workspace\Domain\Entities\WorkspaceEntity;

class CreateDataUnitCommandHandler
{
    public function __construct(
        private AssistantRepositoryInterface $repo,
        private CdnInterface $cdn,
        private DocumentReader $reader,
        private AiServiceFactoryInterface $factory,
        private VectorStoreInterface $store,
        private EventDispatcherInterface $dispatcher,

        #[Inject('option.embeddings.model')]
        private string $embeddingModel = 'text-embedding-3-small',

        #[Inject('option.billing.charge_for_data_units')]
        private bool $chargeForDataUnits = true,

        #[Inject('option.billing.negative_balance_enabled')]
        private bool $negativeBalance = false,
    ) {}

    public function handle(CreateDataUnitCommand $cmd): AbstractDataUnitEntity
    {
        $assistant = $cmd->assistant instanceof AssistantEntity
            ? $cmd->assistant
            : $this->repo->ofId($cmd->assistant);

        if ($cmd->file) {
            $resource = $this->getFileResourceEntity($cmd->file, $assistant);
            $cost = $resource->getCost();
            $assistant->addDataUnit($resource);
            $this->deductCost($cost, $cmd->workspace);
            return $resource;
        }

        if ($cmd->url) {
            $resource = $this->getPageResourceEntity($cmd->url, $assistant);
            $cost = $resource->getCost();
            $assistant->addDataUnit($resource);
            $this->deductCost($cost, $cmd->workspace);
            return $resource;
        }

        throw new RuntimeException('Invalid command');
    }

    private function deductCost(
        CreditCount $cost,
        ?WorkspaceEntity $workspace = null
    ): void {
        if (!$workspace || !$this->chargeForDataUnits) {
            return;
        }

        $workspace->deductCredit($cost, $this->negativeBalance);

        // Dispatch event
        $event = new CreditUsageEvent($workspace, $cost);
        $this->dispatcher->dispatch($event);
    }

    /**
     * @throws RuntimeException
     * @throws UnableToWriteFile
     * @throws FilesystemException
     */
    private function getFileResourceEntity(
        UploadedFileInterface $file,
        AssistantEntity $assistant
    ): FileUnitEntity {
        $ext = strtolower(
            pathinfo($file->getClientFilename(), PATHINFO_EXTENSION)
        );

        // Save file to CDN
        $stream = $file->getStream();
        $stream->rewind();
        $name = Uuid::uuid4()->toString() . '.' . $ext;
        $this->cdn->write("/" . $name, $stream->getContents());

        $stream->rewind();
        $contents = $stream->getContents();

        $fe = new FileEntity(
            new Storage($this->cdn->getAdapterLookupKey()),
            new ObjectKey($name),
            new Url($this->cdn->getUrl($name)),
            new Size($file->getSize())
        );

        $resource = new FileUnitEntity($fe);
        $resource->setTitle(
            new Title(
                $file->getClientFilename() ?
                    $this->getHumanReadableFileName($file->getClientFilename())
                    : null
            )
        );

        $embedable = $this->reader->read($contents, $ext);
        if ($embedable) {
            $model = new Model($this->embeddingModel);
            $service = $this->factory->create(EmbeddingServiceInterface::class, $model);
            $resp = $service->generateEmbedding($model, $embedable);

            $embedding = $resp->embedding;
            $this->store->upsert($resource->getId(), $embedding, $assistant);
            $cost = $resp->cost;

            $resource->setCost($cost);
        }

        return $resource;
    }

    private function getHumanReadableFileName(string $fileName): string
    {
        // Remove file extension
        $name = pathinfo($fileName, PATHINFO_FILENAME);

        // Replace underscores and hyphens with spaces
        $name = str_replace(['_', '-'], ' ', $name);

        // Remove multiple consecutive spaces
        $name = preg_replace('/\s+/', ' ', $name);

        // Trim leading and trailing spaces
        $name = trim($name);

        // Capitalize the first letter of each word
        return mb_convert_case(
            $name,
            MB_CASE_TITLE,
            "UTF-8"
        );
    }

    /**
     * @throws ClientExceptionInterface
     * @throws RuntimeException
     */
    private function getPageResourceEntity(
        DatasetUrl $url,
        AssistantEntity $assistant
    ): LinkUnitEntity {
        $embedable = $this->reader->readFromUrl($url->value);
        $embedding = new Embedding();

        $unit = new LinkUnitEntity($url, $embedding);

        if ($embedable) {
            $model = new Model($this->embeddingModel);
            $service = $this->factory->create(EmbeddingServiceInterface::class, $model);
            $resp = $service->generateEmbedding($model, $embedable);

            $embedding = $resp->embedding;
            $this->store->upsert($unit->getId(), $embedding, $assistant);
            $cost = $resp->cost;

            $unit->setCost($cost);
        }

        return $unit;
    }
}
