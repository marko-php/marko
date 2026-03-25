<?php

declare(strict_types=1);

namespace Marko\DevServer\Command;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;
use Marko\DevServer\Detection\DockerDetector;
use Marko\DevServer\Detection\FrontendDetector;
use Marko\DevServer\Detection\PubSubDetector;
use Marko\DevServer\Exceptions\DevServerException;
use Marko\DevServer\Process\PidFile;
use Marko\DevServer\Process\ProcessEntry;
use Marko\DevServer\Process\ProcessManager;

/** @noinspection PhpUnused */
#[Command(name: 'dev:up', description: 'Start the development environment', aliases: ['up'])]
readonly class DevUpCommand implements CommandInterface
{
    public function __construct(
        private ConfigRepositoryInterface $config,
        private DockerDetector $dockerDetector,
        private FrontendDetector $frontendDetector,
        private PubSubDetector $pubsubDetector,
        private PidFile $pidFile,
        private ProcessManager $processManager,
        private ProjectPaths $paths,
    ) {}

    /**
     * @throws ConfigNotFoundException|DevServerException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $port = (int) ($input->getOption('port') ?? $input->getOption('p') ?? $this->config->getInt('dev.port'));
        $foreground = $input->hasOption('foreground') || $input->hasOption('f');
        $detach = !$foreground && ($input->hasOption('detach') || $input->hasOption('d') || $this->config->getBool(
            'dev.detach',
        ));
        $dockerConfig = $this->config->get('dev.docker');
        $frontendConfig = $this->config->get('dev.frontend');
        $pubsubConfig = $this->config->get('dev.pubsub');

        // Guard: check if services are already running
        $existingEntries = $this->pidFile->read();
        foreach ($existingEntries as $entry) {
            if ($this->pidFile->isRunning($entry->pid)) {
                throw new DevServerException(
                    message: 'Development environment is already running.',
                    context: "Process '{$entry->name}' (PID {$entry->pid}) is still active",
                    suggestion: "Stop the existing environment first with 'marko down', then run 'marko up' again.",
                );
            }
        }

        $indexPath = $this->paths->base . '/public/index.php';
        if (!file_exists($indexPath)) {
            throw new DevServerException(
                message: 'Cannot start PHP server: public/index.php not found.',
                context: "While starting PHP development server (expected at $indexPath)",
                suggestion: "Create public/index.php with:\n\n" .
                    "<?php\n\n" .
                    "declare(strict_types=1);\n\n" .
                    "require __DIR__ . '/../vendor/autoload.php';\n\n" .
                    "use Marko\\Core\\Application;\n\n" .
                    "\$app = Application::boot(dirname(__DIR__));\n" .
                    "\$app->handleRequest();\n",
            );
        }

        $output->writeLine('Starting development environment...');

        $entries = [];

        // Docker
        if ($dockerConfig !== false) {
            $dockerCommand = is_string($dockerConfig)
                ? $dockerConfig
                : $this->dockerDetector->detect()['upCommand'] ?? null;

            if ($dockerCommand !== null) {
                $output->writeLine("  Starting Docker: $dockerCommand");
                $pid = $this->processManager->start('docker', $dockerCommand);
                $entries[] = new ProcessEntry(
                    name: 'docker',
                    pid: $pid,
                    command: $dockerCommand,
                    port: 0,
                    startedAt: date('c'),
                );
            }
        }

        // Frontend
        if ($frontendConfig !== false) {
            $frontendCommand = is_string($frontendConfig)
                ? $frontendConfig
                : $this->frontendDetector->detect();

            if ($frontendCommand !== null) {
                $output->writeLine("  Starting frontend: $frontendCommand");
                $pid = $this->processManager->start('frontend', $frontendCommand);
                $entries[] = new ProcessEntry(
                    name: 'frontend',
                    pid: $pid,
                    command: $frontendCommand,
                    port: 0,
                    startedAt: date('c'),
                );
            }
        }

        // Pub/Sub listener
        if ($pubsubConfig !== false) {
            $pubsubCommand = is_string($pubsubConfig)
                ? $pubsubConfig
                : $this->pubsubDetector->detect();

            if ($pubsubCommand !== null) {
                $output->writeLine("  Starting pub/sub listener: $pubsubCommand");
                $pid = $this->processManager->start('pubsub', $pubsubCommand);
                $entries[] = new ProcessEntry(
                    name: 'pubsub',
                    pid: $pid,
                    command: $pubsubCommand,
                    port: 0,
                    startedAt: date('c'),
                );
            }
        }

        // Custom processes
        /** @var array<string, string> $processes */
        $processes = $this->config->get('dev.processes');
        foreach ($processes as $name => $processCommand) {
            $output->writeLine("  Starting $name: $processCommand");
            $pid = $this->processManager->start($name, $processCommand);
            $entries[] = new ProcessEntry(
                name: $name,
                pid: $pid,
                command: $processCommand,
                port: 0,
                startedAt: date('c'),
            );
        }

        // PHP server (always) — multiple workers needed for SSE
        $phpCommand = "env PHP_CLI_SERVER_WORKERS=4 php -S localhost:$port -t public/";
        $output->writeLine("  Starting PHP server: php -S localhost:$port");
        $pid = $this->processManager->start('php', $phpCommand);

        // Verify the PHP server is still alive — if it died immediately, the port is likely in use
        usleep(100000); // 100ms — give the server time to attempt binding
        if (!$this->processManager->isRunning('php')) {
            throw DevServerException::portInUse($port);
        }

        $entries[] = new ProcessEntry(
            name: 'php',
            pid: $pid,
            command: $phpCommand,
            port: $port,
            startedAt: date('c'),
        );

        if ($detach) {
            $this->pidFile->write($entries);
            $output->writeLine('Development environment started in background.');
            $output->writeLine("Run 'marko dev:status' to check status.");
            $output->writeLine("Run 'marko dev:down' to stop.");
        } else {
            $output->writeLine('Development environment running. Press Ctrl+C to stop.');
            $this->processManager->runForeground();
        }

        return 0;
    }
}
