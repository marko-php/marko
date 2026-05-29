<?php

declare(strict_types=1);

namespace Marko\CodeIndexer\Commands;

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\CodeIndexer\Exceptions\IndexCacheException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'indexer:rebuild', description: 'Rebuild the Marko code index cache')]
class RebuildIndexCommand implements CommandInterface
{
    private const string CACHE_FILE = '.marko/index.cache';

    public function __construct(
        private readonly IndexCache $cache,
        private readonly ?Output $errorOutput = null,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        try {
            $this->cache->build();
        } catch (IndexCacheException $e) {
            $errOut = $this->errorOutput ?? $output;
            $errOut->writeLine('Error: ' . $e->getMessage());

            return 1;
        }

        $modules = count($this->cache->getModules());
        $observers = count($this->cache->getObservers());
        $plugins = count($this->cache->getPlugins());
        $preferences = count($this->cache->getPreferences());
        $commands = count($this->cache->getCommands());
        $routes = count($this->cache->getRoutes());
        $configKeys = count($this->cache->getConfigKeys());
        $templates = count($this->cache->getTemplates());
        $translationKeys = count($this->cache->getTranslationKeys());

        $output->writeLine('Index rebuilt successfully.');
        $output->writeLine('');
        $output->writeLine("  $modules modules");
        $output->writeLine("  $observers observers");
        $output->writeLine("  $plugins plugins");
        $output->writeLine("  $preferences preferences");
        $output->writeLine("  $commands commands");
        $output->writeLine("  $routes routes");
        $output->writeLine("  $configKeys config keys");
        $output->writeLine("  $templates templates");
        $output->writeLine("  $translationKeys translation keys");
        $output->writeLine('');
        $output->writeLine('Cache written to: ' . self::CACHE_FILE);

        return 0;
    }
}
