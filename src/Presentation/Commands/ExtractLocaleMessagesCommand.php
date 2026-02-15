<?php

declare(strict_types=1);

namespace Presentation\Commands;

use Easy\Container\Attributes\Inject;
use Gettext\Generator\PoGenerator;
use Gettext\Loader\PoLoader;
use Gettext\Merge;
use Gettext\Scanner\PhpScanner;
use Gettext\Translations;
use Plugin\Domain\Repositories\PluginRepositoryInterface;
use Shared\Infrastructure\I18n\Twig\Scanner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Twig\Environment;

#[AsCommand(name: 'app:locale:extract')]
class ExtractLocaleMessagesCommand extends Command
{
    public function __construct(
        private Environment $twig,
        private PluginRepositoryInterface $repo,

        #[Inject('config.dirs.root')]
        private string $rootDir,

        #[Inject('config.dirs.locale')]
        private string $localeDir,

        #[Inject('config.dirs.views')]
        private string $viewsDir,

        #[Inject('config.dirs.extensions')]
        private string $extDir,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        // Create a single translations object for the main domain
        $mainTranslations = Translations::create('messages');

        // Extract from main views
        $this->extractToTranslations(
            $mainTranslations,
            $this->rootDir,
            $this->viewsDir . '/*/*.twig',
        );

        // Extract from PHP files
        $this->extractToTranslations(
            $mainTranslations,
            $this->rootDir,
            $this->rootDir . '/src/*/*.php',
        );

        // Save main translations
        $this->saveTranslations($mainTranslations, $this->localeDir);

        // Handle plugins separately
        foreach ($this->repo as $plugin) {
            $pluginTranslations = Translations::create(
                $plugin->context->type->value == 'theme'
                    ? 'theme'
                    : $plugin->context->name->value
            );

            $this->extractToTranslations(
                $pluginTranslations,
                $this->extDir . '/' . $plugin->context->name->value,
                $this->extDir . '/' . $plugin->context->name->value . '/*/*.twig'
            );

            $this->saveTranslations(
                $pluginTranslations,
                $this->extDir . '/' . $plugin->context->name->value . '/locale'
            );
        }

        return Command::SUCCESS;
    }

    protected function extractToTranslations(
        Translations $translations,
        string $baseDir,
        string $scanGlobPattern
    ): void {
        // Create scanner
        $scanner = new Scanner(
            $this->twig,
            $baseDir,
            $translations
        );

        $scanner->addReferences(true);

        if (str_ends_with($scanGlobPattern, '.php')) {
            $scanner = new PhpScanner($translations);
            $scanner->addReferences(false);
        }

        // Set default domain, so any translations with no domain specified,
        // will be added to that domain
        if ($translations->getDomain()) {
            $scanner->setDefaultDomain($translations->getDomain());
        }

        // Scan files - this will add to the existing translations object
        foreach ($this->glob($scanGlobPattern) as $file) {
            $scanner->scanFile($file);
        }
    }

    protected function saveTranslations(
        Translations $translations,
        string $localeDir
    ): void {
        // Get possible languages
        $languages = array_map(
            fn($dir) => basename($dir),
            glob($localeDir . '/*', GLOB_ONLYDIR)
        );

        // Save the translations in .po files
        $generator = new PoGenerator();
        $loader = new PoLoader();

        foreach ($languages as $language) {
            if (!file_exists("{$localeDir}/{$language}/LC_MESSAGES")) {
                mkdir("{$localeDir}/{$language}/LC_MESSAGES", 0777, true);
            }

            $domain = $translations->getDomain();
            if (file_exists("{$localeDir}/{$language}/LC_MESSAGES/{$domain}.po")) {
                $finalTranslations = $translations->mergeWith(
                    $loader->loadFile("{$localeDir}/{$language}/LC_MESSAGES/{$domain}.po"),
                    Merge::SCAN_AND_LOAD
                );
            } else {
                $finalTranslations = clone $translations;
            }

            $generator->generateFile(
                $finalTranslations,
                "{$localeDir}/{$language}/LC_MESSAGES/{$domain}.po"
            );
        }
    }

    private function glob($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $files = array_merge($files, $this->glob($dir . '/' . basename($pattern), $flags));
        }

        return $files;
    }
}
