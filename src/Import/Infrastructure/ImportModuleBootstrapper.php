<?php

declare(strict_types=1);

namespace Import\Infrastructure;

use Application;
use Cron\Domain\Events\CronEvent;
use Easy\EventDispatcher\Mapper\ArrayMapper;
use Import\Domain\Repositories\ImportJobRepositoryInterface;
use Import\Infrastructure\Listeners\ProcessImportJobs;
use Import\Infrastructure\Repositories\DoctrineOrm\ImportJobRepository;
use Override;
use Shared\Infrastructure\BootstrapperInterface;

class ImportModuleBootstrapper implements BootstrapperInterface
{
    public function __construct(
        private Application $app,
        private ArrayMapper $mapper,
    ) {}

    #[Override]
    public function bootstrap(): void
    {
        // Register repository implementation
        $this->app->set(
            ImportJobRepositoryInterface::class,
            ImportJobRepository::class
        );

        // Register event listeners
        $this->mapper->addEventListener(
            CronEvent::class,
            ProcessImportJobs::class
        );
    }
}
