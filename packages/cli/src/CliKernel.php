<?php

declare(strict_types=1);

namespace Marko\Cli;

use Closure;
use Marko\Cli\Exceptions\CliException;
use Marko\Cli\Exceptions\ProjectNotFoundException;
use Marko\Core\Application;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Exceptions\MarkoException;
use Throwable;

readonly class CliKernel
{
    /** Commands that boot without the discovery cache, so they can rebuild or remove it. */
    private const array LIVE_DISCOVERY_COMMANDS = ['discovery:cache', 'discovery:clear'];

    /** @var Closure(string): object */
    private Closure $applicationFactory;

    private Output $output;

    /**
     * @param Closure(string): object|null $applicationFactory
     */
    public function __construct(
        private ProjectFinder $projectFinder,
        ?Closure $applicationFactory = null,
        ?Output $output = null,
    ) {
        $this->applicationFactory = $applicationFactory ?? fn (string $projectRoot): Application => new Application(
            vendorPath: $projectRoot . '/vendor',
            modulesPath: $projectRoot . '/modules',
            appPath: $projectRoot . '/app',
        );
        $this->output = $output ?? new Output();
    }

    /**
     * @param array<int, string> $argv
     *
     * @throws ProjectNotFoundException
     */
    public function run(
        array $argv,
    ): int {
        try {
            return $this->doRun($argv);
        } catch (CliException|MarkoException $e) {
            $this->displayCliException($e);

            return 1;
        } catch (Throwable $e) {
            $this->displayException($e);

            return 1;
        }
    }

    /**
     * @param array<int, string> $argv
     *
     * @throws ProjectNotFoundException
     */
    private function doRun(
        array $argv,
    ): int {
        $projectRoot = $this->projectFinder->find();

        if ($projectRoot === null) {
            throw ProjectNotFoundException::fromDirectory(getcwd() ?: '.');
        }

        // Load the project's autoloader
        require_once $projectRoot . '/vendor/autoload.php';

        // Parse input and create output
        $input = new Input($argv);

        // Get command name (default to 'list' if none provided)
        $commandName = $input->getCommand() ?? 'list';

        // Create and boot the application. The discovery cache commands boot from live
        // discovery so they still work when the cache is stale, corrupt or outdated.
        $app = ($this->applicationFactory)($projectRoot);
        $app->initialize(!in_array($commandName, self::LIVE_DISCOVERY_COMMANDS, true));

        // Delegate to command runner
        return $app->commandRunner->run($commandName, $input, $this->output);
    }

    private function displayCliException(
        CliException|MarkoException $e,
    ): void {
        $this->output->writeLine('');
        $this->output->writeLine("Error: {$e->getMessage()}");

        if ($e->getContext() !== '') {
            $this->output->writeLine("  Context: {$e->getContext()}");
        }

        if ($e->getSuggestion() !== '') {
            $this->output->writeLine("  Suggestion: {$e->getSuggestion()}");
        }

        $this->output->writeLine('');
    }

    private function displayException(
        Throwable $e,
    ): void {
        $this->output->writeLine('');
        $this->output->writeLine("Error: {$e->getMessage()}");
        $this->output->writeLine('');
    }
}
