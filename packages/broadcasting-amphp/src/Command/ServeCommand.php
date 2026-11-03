<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp\Command;

use function Amp\async;

use Marko\Amphp\AmphpConfig;
use Marko\Amphp\EventLoopRunner;
use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;
use Marko\Broadcasting\Amphp\Server\AmphpSseServer;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Revolt\EventLoop\UnsupportedFeatureException;

/**
 * Runs the native SSE server until SIGINT or SIGTERM, then shuts it down gracefully within
 * amphp.shutdown_timeout seconds.
 *
 * @noinspection PhpUnused
 */
#[Command(
    name: 'broadcasting:serve',
    description: 'Start the native async SSE broadcasting server',
    options: ['host', 'port'],
)]
readonly class ServeCommand implements CommandInterface
{
    public function __construct(
        private EventLoopRunner $runner,
        private AmphpConfig $amphpConfig,
        private AmphpSseServer $amphpSseServer,
    ) {}

    /**
     * @throws AmphpBroadcastException|ConfigNotFoundException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $host = $input->getOption('host');
        $port = $this->port($input->getOption('port'));
        $shutdownTimeout = (float) $this->amphpConfig->shutdownTimeout();

        $this->amphpSseServer->start($host, $port);

        $stopping = false;
        $shutdown = function () use (&$stopping, $output, $shutdownTimeout): void {
            if ($stopping) {
                return;
            }

            $stopping = true;
            $output->writeLine('Stopping broadcasting server...');

            async(function () use ($shutdownTimeout): void {
                $this->amphpSseServer->stop($shutdownTimeout);
                $this->runner->stop();
            })->ignore();
        };

        try {
            $this->runner->onSignal(SIGINT, $shutdown);
            $this->runner->onSignal(SIGTERM, $shutdown);
        } catch (UnsupportedFeatureException $e) {
            $this->amphpSseServer->stop(0.0);

            throw AmphpBroadcastException::signalsUnsupported($e);
        }

        $output->writeLine('Broadcasting server listening on ' . $this->amphpSseServer->address());
        $output->writeLine('Press Ctrl+C to stop.');
        $this->runner->run();
        $output->writeLine('Broadcasting server stopped.');

        return 0;
    }

    /**
     * @throws AmphpBroadcastException
     */
    private function port(?string $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $port = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 65535]]);

        if ($port === false) {
            throw AmphpBroadcastException::invalidPort($value);
        }

        return $port;
    }
}
