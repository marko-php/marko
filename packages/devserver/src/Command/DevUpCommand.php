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
use Marko\DevServer\Process\ServerHost;
use Throwable;

/** @noinspection PhpUnused */
#[Command(
    name: 'dev:up',
    description: 'Start the development environment',
    aliases: ['up'],
    flags: ['foreground', 'f', 'detach', 'd'],
    options: ['port', 'p', 'host'],
)]
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
     * Start every configured service, or none of them.
     *
     * If any service fails to start, every service started so far is stopped and the
     * original error is rethrown.
     *
     * @throws ConfigNotFoundException|DevServerException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $port = (int) ($input->getOption('port') ?? $input->getOption('p') ?? $this->config->getInt('dev.port'));
        $foreground = $input->hasOption('foreground') || $input->hasOption('f');
        $host = ServerHost::fromString($input->getOption('host') ?? $this->config->getString('dev.host'));
        $detach = !$foreground && ($input->hasOption('detach') || $input->hasOption('d') || $this->config->getBool(
            'dev.detach',
        ));

        // Guard: check if services are already running
        $existingEntries = $this->pidFile->read();
        foreach ($existingEntries as $entry) {
            if ($this->pidFile->isRunning($entry->pid)) {
                throw new DevServerException(
                    message: 'Development environment is already running.',
                    context: "Process '$entry->name' (PID $entry->pid) is still active",
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

        // Fail before starting anything if the PHP server's port is already taken
        if (!$this->processManager->isPortAvailable($host->address, $port)) {
            throw DevServerException::portInUse($port);
        }

        $output->writeLine('Starting development environment...');

        try {
            $entries = $this->startServices($output, $detach, $host, $port);

            if ($detach) {
                $this->pidFile->write($entries);
            }
        } catch (Throwable $e) {
            $this->rollBack($output, $e);
        }

        if ($detach) {
            $output->writeLine('Development environment started in background.');
            $output->writeLine("Run 'marko dev:status' to check status.");
            $output->writeLine("Run 'marko dev:down' to stop.");
        } else {
            $output->writeLine('Development environment running. Press Ctrl+C to stop.');
            $this->processManager->runForeground();
        }

        return 0;
    }

    /**
     * Start Docker, the frontend, the pub/sub listener, custom processes and the PHP server, in that order.
     *
     * @return list<ProcessEntry>
     * @throws ConfigNotFoundException|DevServerException
     */
    private function startServices(
        Output $output,
        bool $detach,
        ServerHost $host,
        int $port,
    ): array {
        $entries = [];
        $startProcess = $detach
            ? $this->processManager->startDetached(...)
            : $this->processManager->start(...);

        $start = function (string $name, string $command, string $label) use ($output, $startProcess, &$entries): void {
            $output->writeLine("  Starting $label: $command");
            $pid = $startProcess($name, $command);
            $entries[] = new ProcessEntry(
                name: $name,
                pid: $pid,
                command: $command,
                port: 0,
                startedAt: date('c'),
            );
        };

        // Docker
        $dockerConfig = $this->config->get('dev.docker');
        if ($dockerConfig !== false) {
            $dockerCommand = is_string($dockerConfig)
                ? $dockerConfig
                : $this->dockerDetector->detect()['upCommand'] ?? null;

            if ($dockerCommand !== null) {
                $start('docker', $dockerCommand, 'Docker');
            }
        }

        // Frontend
        $frontendConfig = $this->config->get('dev.frontend');
        if ($frontendConfig !== false) {
            $frontendCommand = is_string($frontendConfig)
                ? $frontendConfig
                : $this->frontendDetector->detect();

            if ($frontendCommand !== null) {
                $start('frontend', $frontendCommand, 'frontend');
            }
        }

        // Pub/Sub listener
        $pubsubConfig = $this->config->get('dev.pubsub');
        if ($pubsubConfig !== false) {
            $pubsubCommand = is_string($pubsubConfig)
                ? $pubsubConfig
                : $this->pubsubDetector->detect();

            if ($pubsubCommand !== null) {
                $start('pubsub', $pubsubCommand, 'pub/sub listener');
            }
        }

        // Custom processes
        /** @var array<string, string> $processes */
        $processes = $this->config->get('dev.processes');
        foreach ($processes as $name => $processCommand) {
            $start((string) $name, $processCommand, (string) $name);
        }

        // PHP server (always) — multiple workers needed for SSE
        $address = $host->forUri() . ":$port";
        $phpCommand = "env PHP_CLI_SERVER_WORKERS=4 php -S $address -t public/";
        $output->writeLine("  Starting PHP server: php -S $address");
        $pid = $startProcess('php', $phpCommand);

        // In foreground mode, wait until the PHP server accepts connections.
        // In detached mode, startDetached() already fails on any exit during its probe window.
        if (!$detach && !$this->processManager->waitUntilAccepting('php', $host->address, $port)) {
            throw $this->serverExitedException($host, $port);
        }

        $entries[] = new ProcessEntry(
            name: 'php',
            pid: $pid,
            command: $phpCommand,
            port: $port,
            startedAt: date('c'),
            host: $host->address,
        );

        return $entries;
    }

    /**
     * Explain why the PHP server exited before it accepted a connection.
     *
     * It lost the bind when the port is taken now (another process grabbed it after the
     * check before startup). Anything else — an address that is not local, a syntax
     * error, a missing extension — is reported with the server's exit code and output.
     */
    private function serverExitedException(
        ServerHost $host,
        int $port,
    ): DevServerException {
        $exitCode = $this->processManager->getExitCode('php');
        $serverOutput = $this->processManager->collectOutput('php');

        if (!$this->processManager->isPortAvailable($host->address, $port)) {
            return DevServerException::portInUse($port);
        }

        return DevServerException::serverExited($host->address, $port, $exitCode, $serverOutput);
    }

    /**
     * Stop every service started so far, then rethrow the failure.
     *
     * A PID file left by an earlier run is removed too: the guard above has already
     * confirmed none of its processes are running.
     *
     * @throws DevServerException|Throwable
     */
    private function rollBack(
        Output $output,
        Throwable $failure,
    ): never {
        $output->writeLine('Startup failed. Stopping the services that already started...');
        $this->pidFile->clear();

        try {
            $this->processManager->stopAll();
        } catch (DevServerException) {
            throw DevServerException::rollbackFailed($failure, $this->processManager->getPids());
        }

        throw $failure;
    }
}
