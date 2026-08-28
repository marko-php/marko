<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;
use Marko\Roadrunner\Binary\BinaryLocatorInterface;
use Marko\Roadrunner\Config\RrYamlTemplate;
use Marko\Roadrunner\Exceptions\RoadRunnerException;
use Marko\Roadrunner\Process\ProcessRunnerInterface;

/** @noinspection PhpUnused */
#[Command(name: 'rr:serve', description: 'Start the application under the RoadRunner application server')]
readonly class ServeCommand implements CommandInterface
{
    private const string DEFAULT_CONFIG_FILE = '.rr.yaml';

    public function __construct(
        private BinaryLocatorInterface $binaryLocator,
        private ProcessRunnerInterface $processRunner,
        private ProjectPaths $paths,
    ) {}

    /**
     * @throws RoadRunnerException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $binary = $this->binaryLocator->locate();

        if ($binary === null) {
            throw RoadRunnerException::binaryNotFound();
        }

        $configPath = $this->resolveConfigPath($input);

        if (file_exists($configPath)) {
            $output->writeLine("Using existing config: $configPath");
        } else {
            file_put_contents($configPath, RrYamlTemplate::render($this->paths->base));
            $output->writeLine("Created default config: $configPath");
        }

        $output->writeLine("Starting RoadRunner server ($binary serve -c $configPath)...");

        return $this->processRunner->run($binary . ' serve -c ' . escapeshellarg($configPath));
    }

    private function resolveConfigPath(Input $input): string
    {
        $configOption = $input->getOption('config') ?? self::DEFAULT_CONFIG_FILE;

        return str_starts_with($configOption, '/')
            ? $configOption
            : $this->paths->base . '/' . $configOption;
    }
}
