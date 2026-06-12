<?php

declare(strict_types=1);

namespace Marko\Amphp\Command;

use Marko\Amphp\AmphpConfig;
use Marko\Amphp\EventLoopRunner;
use Marko\Amphp\Exceptions\AmphpException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PubSub\SubscriberInterface;

/** @noinspection PhpUnused */
#[Command(name: 'pubsub:listen', description: 'Start the pub/sub listener')]
readonly class PubSubListenCommand implements CommandInterface
{
    public function __construct(
        private EventLoopRunner $runner,
        private AmphpConfig $amphpConfig,
        private SubscriberInterface $subscriber,
        private MessageHandlerInterface $messageHandler,
    ) {}

    /**
     * @throws AmphpException|ConfigNotFoundException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $channels = $this->amphpConfig->channels();

        if ($channels === []) {
            throw AmphpException::noChannelsConfigured();
        }

        $subscription = $this->subscriber->subscribe(...$channels);

        $this->runner->onSignal(SIGINT, function () use ($subscription): void {
            $subscription->cancel();
            $this->runner->delay(
                (float) $this->amphpConfig->shutdownTimeout(),
                function (): void {
                    $this->runner->stop();
                },
            );
            $this->runner->stop();
        });

        $this->runner->queue(function () use ($subscription): void {
            foreach ($subscription->getIterator() as $message) {
                $this->messageHandler->handle($message);
            }
        });

        $output->writeLine('Starting pub/sub listener...');
        $output->writeLine('Press Ctrl+C to stop.');
        $this->runner->run();
        $output->writeLine('Listener stopped.');

        return 0;
    }
}
