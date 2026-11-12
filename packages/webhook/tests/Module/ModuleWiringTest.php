<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Module;

use Marko\Cache\Contracts\CacheInterface;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Core\Container\Container;
use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Webhook\Contracts\WebhookReceiverInterface;
use Marko\Webhook\Contracts\WebhookReplayGuardInterface;
use Marko\Webhook\Exceptions\InvalidSignatureException;
use Marko\Webhook\Middleware\WebhookEndpointMiddleware;
use Marko\Webhook\Receiving\CacheReplayGuard;
use Marko\Webhook\Receiving\WebhookReceiver;
use Marko\Webhook\Sending\WebhookSignature;
use Psr\Clock\ClockInterface;
use ReflectionProperty;

/**
 * @return array<string, mixed>
 */
function webhookModule(): array
{
    return require dirname(__DIR__, 2) . '/module.php';
}

function webhookModuleContainer(
    bool $replayProtection,
    CacheInterface $cache,
): Container {
    $container = new Container();
    $container->instance(ConfigRepositoryInterface::class, new FakeConfigRepository([
        'webhook.timeout' => 30,
        'webhook.max_retries' => 3,
        'webhook.retry_delay' => 60,
        'webhook.timestamp_tolerance' => 300,
        'webhook.max_body_bytes' => 1048576,
        'webhook.replay_protection' => $replayProtection,
    ]));
    $container->instance(ClockInterface::class, new FakeClock('@1767268800'));
    $container->instance(CacheInterface::class, $cache);

    foreach (webhookModule()['bindings'] as $abstract => $concrete) {
        $container->bind($abstract, $concrete);
    }

    foreach (webhookModule()['arguments'] as $class => $arguments) {
        $container->setArguments($class, $arguments);
    }

    return $container;
}

function webhookModuleRequest(): Request
{
    $body = '{"event":"order.created"}';

    return new Request(
        server: [
            'HTTP_X_WEBHOOK_ID' => 'delivery-1',
            'HTTP_X_WEBHOOK_SIGNATURE' => WebhookSignature::sign($body, 'my-signing-secret', 1767268800, 'delivery-1'),
            'HTTP_X_WEBHOOK_TIMESTAMP' => '1767268800',
        ],
        body: $body,
    );
}

describe('Webhook module wiring', function (): void {
    it(
        'registers WebhookEndpointMiddleware as global middleware so #[WebhookEndpoint] needs nothing else',
        function (): void {
            expect(webhookModule()['globalMiddleware'])->toBe([WebhookEndpointMiddleware::class]);
        },
    );

    it('declares only the replayGuard argument for WebhookReceiver', function (): void {
        $module = webhookModule();

        expect($module['bindings'])->not->toHaveKey(WebhookReceiver::class)
            ->and(array_keys($module['arguments'][WebhookReceiver::class]))->toBe(['replayGuard']);
    });

    it('builds WebhookReceiver without a replay guard when replay protection is off', function (): void {
        $receiver = webhookModuleContainer(false, $this->createStub(CacheInterface::class))
            ->get(WebhookReceiver::class);

        expect(new ReflectionProperty(WebhookReceiver::class, 'replayGuard')->getValue($receiver))->toBeNull();
    });

    it('builds WebhookReceiver with the replay guard when replay protection is on', function (): void {
        $receiver = webhookModuleContainer(true, $this->createStub(CacheInterface::class))
            ->get(WebhookReceiver::class);

        expect(new ReflectionProperty(WebhookReceiver::class, 'replayGuard')->getValue($receiver))
            ->toBeInstanceOf(CacheReplayGuard::class);
    });

    it(
        'honours replay protection when WebhookReceiver is resolved through WebhookReceiverInterface',
        function (): void {
            $receiver = webhookModuleContainer(false, $this->createStub(CacheInterface::class))
                ->get(WebhookReceiverInterface::class);

            expect($receiver)->toBeInstanceOf(WebhookReceiver::class)
                ->and(new ReflectionProperty(WebhookReceiver::class, 'replayGuard')->getValue($receiver))
                ->toBeNull();
        },
    );

    it('binds the receiver interface and a cache-backed replay guard', function (): void {
        $container = webhookModuleContainer(true, $this->createStub(CacheInterface::class));

        expect($container->get(WebhookReceiverInterface::class))->toBeInstanceOf(WebhookReceiver::class)
            ->and($container->get(WebhookReplayGuardInterface::class))->toBeInstanceOf(CacheReplayGuard::class);
    });

    it('gives the container-built receiver the replay guard when webhook.replay_protection is on', function (): void {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('increment')->willReturnOnConsecutiveCalls(1, 2);
        $receiver = webhookModuleContainer(true, $cache)->get(WebhookReceiver::class);

        $receiver->receive(webhookModuleRequest(), 'my-signing-secret');

        expect(fn () => $receiver->receive(webhookModuleRequest(), 'my-signing-secret'))
            ->toThrow(InvalidSignatureException::class, 'was already received');
    });

    it('does not touch the cache when webhook.replay_protection is off', function (): void {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('increment');
        $receiver = webhookModuleContainer(false, $cache)->get(WebhookReceiver::class);

        $receiver->receive(webhookModuleRequest(), 'my-signing-secret');

        expect($receiver->receive(webhookModuleRequest(), 'my-signing-secret'))->toBe(['event' => 'order.created']);
    });
});
