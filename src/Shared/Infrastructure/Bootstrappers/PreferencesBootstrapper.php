<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Bootstrappers;

use Ai\Domain\Embedding\VectorStoreInterface;
use Ai\Infrastructure\Embedding\FileVectorStore;
use Application;
use Billing\Infrastructure\Currency\RateProviderCollectionInterface;
use Billing\Infrastructure\Currency\RateProviderInterface;
use Billing\Infrastructure\Currency\RateProviders\NullRateProvider;
use Easy\Container\Attributes\Inject;
use Override;
use Shared\Infrastructure\BootstrapperInterface;
use Shared\Infrastructure\Collections\ServiceCollectionInterface;
use Shared\Infrastructure\Collections\ServiceNotFoundException;
use Throwable;

class PreferencesBootstrapper implements BootstrapperInterface
{
    public function __construct(
        private Application $app,
        private ServiceCollectionInterface $serviceCollection,
        private RateProviderCollectionInterface $rateProviderCollection,

        #[Inject('option.embeddings.adapter')]
        private string $embeddingsAdapterKey = FileVectorStore::LOOKUP_KEY,

        #[Inject('option.currency.provider')]
        private ?string $currencyProviderKey = null,
    ) {}

    #[Override]
    public function bootstrap(): void
    {
        $this->defineEmbeddingsAdapter();
        $this->defineRateProvider();
    }

    private function defineEmbeddingsAdapter(): void
    {
        $adapter = null;
        $collection = $this->serviceCollection;

        try {
            $adapter = $collection->get(
                $this->embeddingsAdapterKey,
                VectorStoreInterface::class
            );
        } catch (ServiceNotFoundException $th) {
            // Vector store adapter not found
        }

        if (!$adapter) {
            $adapter = $collection->get(
                FileVectorStore::LOOKUP_KEY,
                VectorStoreInterface::class
            );
        }

        $this->app->set(VectorStoreInterface::class, $adapter);
    }

    private function defineRateProvider(): void
    {
        $provider = null;
        $collection = $this->rateProviderCollection;

        try {
            $provider = $collection->get($this->currencyProviderKey);
        } catch (Throwable $th) {
            // Currency provider not found
        }

        if (!$provider) {
            /** @var RateProviderInterface */
            $provider = $collection->get(NullRateProvider::LOOKUP_KEY);
        }

        $this->app->set(
            RateProviderInterface::class,
            $provider
        );
    }
}
