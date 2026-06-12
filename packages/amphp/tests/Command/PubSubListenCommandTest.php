<?php

declare(strict_types=1);

use Marko\Amphp\AmphpConfig;
use Marko\Amphp\Command\MessageHandlerInterface;
use Marko\Amphp\Command\PubSubListenCommand;
use Marko\Amphp\EventLoopRunner;
use Marko\Amphp\Exceptions\AmphpException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PubSub\Message;
use Marko\PubSub\SubscriberInterface;
use Marko\PubSub\Subscription;
use Marko\Testing\Fake\FakeConfigRepository;

// Test stubs
class FakeSubscriber implements SubscriberInterface
{
    /** @var array<int, string[]> */
    public array $subscribedChannels = [];

    private ?Subscription $nextSubscription = null;

    public function setNextSubscription(Subscription $subscription): void
    {
        $this->nextSubscription = $subscription;
    }

    public function subscribe(string ...$channels): Subscription
    {
        $this->subscribedChannels[] = $channels;

        return $this->nextSubscription ?? new FakeSubscription([]);
    }

    public function psubscribe(string ...$patterns): Subscription
    {
        return new FakeSubscription([]);
    }
}

class FakeSubscription implements Subscription
{
    /** @var array<int, Message> */
    private array $messages;

    public bool $cancelled = false;

    /**
     * @param array<int, Message> $messages
     */
    public function __construct(array $messages)
    {
        $this->messages = $messages;
    }

    public function getIterator(): Generator
    {
        foreach ($this->messages as $message) {
            yield $message;
        }
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }
}

class FakeMessageHandler implements MessageHandlerInterface
{
    /** @var array<int, Message> */
    public array $handled = [];

    public function handle(Message $message): void
    {
        $this->handled[] = $message;
    }
}

class TestEventLoopRunner extends EventLoopRunner
{
    /** @var array<int, callable> */
    public array $queued = [];

    /** @var array<int, array{signal: int, handler: callable}> */
    public array $signals = [];

    public function queue(callable $callback): void
    {
        $this->queued[] = $callback;
    }

    public function onSignal(
        int $signal,
        callable $handler,
    ): void {
        $this->signals[] = ['signal' => $signal, 'handler' => $handler];
    }

    protected function doRun(): void
    {
        foreach ($this->queued as $callback) {
            $callback();
        }
    }
}

function makeRunner(): TestEventLoopRunner
{
    return new TestEventLoopRunner();
}

function makeOutput(): array
{
    $stream = fopen('php://memory', 'r+');

    return ['stream' => $stream, 'output' => new Output($stream)];
}

function makeInput(): Input
{
    return new Input(['marko', 'pubsub:listen']);
}

it('has Command attribute with name pubsub:listen and description', function (): void {
    $reflection = new ReflectionClass(PubSubListenCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('pubsub:listen')
        ->and($attributes[0]->newInstance()->description)->not->toBeEmpty();
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(PubSubListenCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('starts the event loop via EventLoopRunner when executed', function (): void {
    $runCalled = false;

    $runner = new class ($runCalled) extends EventLoopRunner
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private bool &$runCalled,
        ) {}

        protected function doRun(): void
        {
            $this->runCalled = true;
        }
    };

    ['output' => $output] = makeOutput();
    $input = makeInput();

    $config = new FakeConfigRepository([
        'amphp.shutdown_timeout' => 30,
        'amphp.channels' => ['test-channel'],
    ]);
    $amphpConfig = new AmphpConfig($config);
    $subscriber = new FakeSubscriber();
    $handler = new FakeMessageHandler();

    $command = new PubSubListenCommand($runner, $amphpConfig, $subscriber, $handler);
    $command->execute($input, $output);

    expect($runCalled)->toBeTrue();
});

it('outputs startup message to Output', function (): void {
    $runner = new class () extends EventLoopRunner
    {
        protected function doRun(): void {}
    };

    ['stream' => $stream, 'output' => $output] = makeOutput();
    $input = makeInput();

    $config = new FakeConfigRepository([
        'amphp.shutdown_timeout' => 30,
        'amphp.channels' => ['test-channel'],
    ]);
    $amphpConfig = new AmphpConfig($config);
    $subscriber = new FakeSubscriber();
    $handler = new FakeMessageHandler();

    $command = new PubSubListenCommand($runner, $amphpConfig, $subscriber, $handler);
    $command->execute($input, $output);

    rewind($stream);
    $result = stream_get_contents($stream);

    expect($result)->toContain('Starting pub/sub listener...');
});

it('returns 0 on successful completion', function (): void {
    $runner = new class () extends EventLoopRunner
    {
        protected function doRun(): void {}
    };

    ['output' => $output] = makeOutput();
    $input = makeInput();

    $config = new FakeConfigRepository([
        'amphp.shutdown_timeout' => 30,
        'amphp.channels' => ['test-channel'],
    ]);
    $amphpConfig = new AmphpConfig($config);
    $subscriber = new FakeSubscriber();
    $handler = new FakeMessageHandler();

    $command = new PubSubListenCommand($runner, $amphpConfig, $subscriber, $handler);
    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
});

it('subscribes to the configured pub/sub channels', function (): void {
    $runner = makeRunner();
    $config = new FakeConfigRepository([
        'amphp.shutdown_timeout' => 30,
        'amphp.channels' => ['orders', 'notifications'],
    ]);
    $amphpConfig = new AmphpConfig($config);
    $subscriber = new FakeSubscriber();
    $handler = new FakeMessageHandler();

    ['output' => $output] = makeOutput();
    $input = makeInput();

    $command = new PubSubListenCommand($runner, $amphpConfig, $subscriber, $handler);
    $command->execute($input, $output);

    expect($subscriber->subscribedChannels)->toHaveCount(1)
        ->and($subscriber->subscribedChannels[0])->toBe(['orders', 'notifications']);
});

it('dispatches a received message to the configured handler', function (): void {
    $runner = makeRunner();
    $config = new FakeConfigRepository([
        'amphp.shutdown_timeout' => 30,
        'amphp.channels' => ['orders'],
    ]);
    $amphpConfig = new AmphpConfig($config);
    $subscriber = new FakeSubscriber();
    $handler = new FakeMessageHandler();

    $message = new Message('orders', '{"id":1}');
    $subscription = new FakeSubscription([$message]);
    $subscriber->setNextSubscription($subscription);

    ['output' => $output] = makeOutput();
    $input = makeInput();

    $command = new PubSubListenCommand($runner, $amphpConfig, $subscriber, $handler);
    $command->execute($input, $output);

    expect($handler->handled)->toHaveCount(1)
        ->and($handler->handled[0])->toBe($message);
});

it('throws AmphpException when no channels are configured', function (): void {
    $runner = makeRunner();
    $config = new FakeConfigRepository([
        'amphp.shutdown_timeout' => 30,
        'amphp.channels' => [],
    ]);
    $amphpConfig = new AmphpConfig($config);
    $subscriber = new FakeSubscriber();
    $handler = new FakeMessageHandler();

    ['output' => $output] = makeOutput();
    $input = makeInput();

    $command = new PubSubListenCommand($runner, $amphpConfig, $subscriber, $handler);

    expect(fn () => $command->execute($input, $output))
        ->toThrow(AmphpException::class);
});

it('stops the listener on shutdown signal', function (): void {
    $runner = new class () extends TestEventLoopRunner
    {
        protected function doRun(): void
        {
            // Simulate signal firing: fire the SIGINT handler
            foreach ($this->signals as $registeredSignal) {
                if ($registeredSignal['signal'] === SIGINT) {
                    ($registeredSignal['handler'])();
                    break;
                }
            }
        }
    };

    $config = new FakeConfigRepository([
        'amphp.shutdown_timeout' => 30,
        'amphp.channels' => ['orders'],
    ]);
    $amphpConfig = new AmphpConfig($config);
    $subscriber = new FakeSubscriber();
    $subscription = new FakeSubscription([]);
    $subscriber->setNextSubscription($subscription);
    $handler = new FakeMessageHandler();

    ['output' => $output] = makeOutput();
    $input = makeInput();

    $command = new PubSubListenCommand($runner, $amphpConfig, $subscriber, $handler);
    $command->execute($input, $output);

    expect($subscription->cancelled)->toBeTrue();
});

it('bounds graceful shutdown by the configured shutdown_timeout', function (): void {
    $shutdownTimeout = 5;

    $timeoutUsed = null;

    $runner = new class ($timeoutUsed) extends TestEventLoopRunner
    {
        public function __construct(
            /** @noinspection PhpPropertyOnlyWrittenInspection - Reference property modifies external variable */
            private mixed &$timeoutUsed,
        ) {}

        public function delay(
            float $seconds,
            callable $callback,
        ): void {
            $this->timeoutUsed = $seconds;
            $callback();
        }

        protected function doRun(): void
        {
            // Simulate signal: fire SIGINT handler
            foreach ($this->signals as $registeredSignal) {
                if ($registeredSignal['signal'] === SIGINT) {
                    ($registeredSignal['handler'])();
                    break;
                }
            }
        }
    };

    $config = new FakeConfigRepository([
        'amphp.shutdown_timeout' => $shutdownTimeout,
        'amphp.channels' => ['orders'],
    ]);
    $amphpConfig = new AmphpConfig($config);
    $subscriber = new FakeSubscriber();
    $subscription = new FakeSubscription([]);
    $subscriber->setNextSubscription($subscription);
    $handler = new FakeMessageHandler();

    ['output' => $output] = makeOutput();
    $input = makeInput();

    $command = new PubSubListenCommand($runner, $amphpConfig, $subscriber, $handler);
    $command->execute($input, $output);

    expect($timeoutUsed)->toBe((float) $shutdownTimeout);
});
