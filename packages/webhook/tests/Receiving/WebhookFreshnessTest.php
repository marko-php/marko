<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Receiving;

use Marko\Routing\Http\Request;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Webhook\Config\WebhookConfig;
use Marko\Webhook\Exceptions\InvalidSignatureException;
use Marko\Webhook\Receiving\WebhookReceiver;
use Marko\Webhook\Receiving\WebhookVerifier;
use Marko\Webhook\Sending\WebhookSignature;

function makeFreshRequest(
    string $secret,
    string $body,
    int $timestamp,
    ?string $overrideSignature = null,
    bool $omitTimestampHeader = false,
): Request {
    $signature = $overrideSignature ?? WebhookSignature::sign($body, $secret, $timestamp);
    $server = ['HTTP_X_WEBHOOK_SIGNATURE' => $signature];

    if (!$omitTimestampHeader) {
        $server['HTTP_X_WEBHOOK_TIMESTAMP'] = (string) $timestamp;
    }

    return new Request(server: $server, body: $body);
}

function makeReceiverWithTolerance(int $tolerance): WebhookReceiver
{
    $config = new WebhookConfig(new FakeConfigRepository([
        'webhook.timeout' => 30,
        'webhook.max_retries' => 3,
        'webhook.retry_delay' => 60,
        'webhook.timestamp_tolerance' => $tolerance,
    ]));

    return new WebhookReceiver(new WebhookVerifier(), $config);
}

describe('WebhookReceiver freshness window', function (): void {
    it('accepts a freshly-signed request whose timestamp is within the tolerance window', function (): void {
        $secret = 'my-secret';
        $body = '{"event":"order.created"}';
        $timestamp = time();

        $receiver = makeReceiverWithTolerance(300);
        $request = makeFreshRequest($secret, $body, $timestamp);

        $result = $receiver->receive($request, $secret);

        expect($result)->toBe(['event' => 'order.created']);
    });

    it('rejects a request whose timestamp is older than the tolerance window', function (): void {
        $secret = 'my-secret';
        $body = '{"event":"order.created"}';
        $timestamp = time() - 301;

        $receiver = makeReceiverWithTolerance(300);
        $request = makeFreshRequest($secret, $body, $timestamp);

        expect(fn () => $receiver->receive($request, $secret))
            ->toThrow(InvalidSignatureException::class);
    });

    it('rejects a request whose timestamp is in the future beyond the tolerance window', function (): void {
        $secret = 'my-secret';
        $body = '{"event":"order.created"}';
        $timestamp = time() + 301;

        $receiver = makeReceiverWithTolerance(300);
        $request = makeFreshRequest($secret, $body, $timestamp);

        expect(fn () => $receiver->receive($request, $secret))
            ->toThrow(InvalidSignatureException::class);
    });

    it('rejects a request when the timestamp header is missing', function (): void {
        $secret = 'my-secret';
        $body = '{"event":"order.created"}';
        $timestamp = time();

        $receiver = makeReceiverWithTolerance(300);
        $request = makeFreshRequest($secret, $body, $timestamp, omitTimestampHeader: true);

        expect(fn () => $receiver->receive($request, $secret))
            ->toThrow(InvalidSignatureException::class);
    });

    it('accepts a request whose timestamp is exactly at the tolerance boundary', function (): void {
        $secret = 'my-secret';
        $body = '{"event":"order.created"}';
        $timestamp = time() - 300;

        $receiver = makeReceiverWithTolerance(300);
        $request = makeFreshRequest($secret, $body, $timestamp);

        $result = $receiver->receive($request, $secret);

        expect($result)->toBe(['event' => 'order.created']);
    });

    it(
        'rejects a request when the timestamp is tampered with but the body signature was computed for a different timestamp',
        function (): void {
            $secret = 'my-secret';
            $body = '{"event":"order.created"}';
            $realTimestamp = time();
            $tamperedTimestamp = time() - 100;

            // Sign with the real timestamp, but send the tampered timestamp header
            $signature = WebhookSignature::sign($body, $secret, $realTimestamp);

            $request = new Request(
                server: [
                    'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                    'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $tamperedTimestamp,
                ],
                body: $body,
            );

            $receiver = makeReceiverWithTolerance(300);

            expect(fn () => $receiver->receive($request, $secret))
                ->toThrow(InvalidSignatureException::class);
        },
    );

    it(
        'reads the tolerance from WebhookConfig (timestamp_tolerance) rather than a hardcoded value',
        function (): void {
            $secret = 'my-secret';
            $body = '{"event":"order.created"}';
            // Use a very tight tolerance of 1 second; a 2-second-old timestamp should be rejected
            $timestamp = time() - 2;

            $receiverTight = makeReceiverWithTolerance(1);
            $request = makeFreshRequest($secret, $body, $timestamp);

            expect(fn () => $receiverTight->receive($request, $secret))
                ->toThrow(InvalidSignatureException::class);

            // With tolerance 300 the same request should pass
            $receiverGenerous = makeReceiverWithTolerance(300);
            $request2 = makeFreshRequest($secret, $body, $timestamp);

            $result = $receiverGenerous->receive($request2, $secret);

            expect($result)->toBe(['event' => 'order.created']);
        },
    );

    it('round-trips a payload signed by WebhookSignature through WebhookReceiver successfully', function (): void {
        $secret = 'my-secret';
        $body = '{"event":"order.created","data":{"order_id":123}}';
        $timestamp = time();

        $signature = WebhookSignature::sign($body, $secret, $timestamp);

        $request = new Request(
            server: [
                'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
            ],
            body: $body,
        );

        $receiver = makeReceiverWithTolerance(300);
        $result = $receiver->receive($request, $secret);

        expect($result)->toBe(['event' => 'order.created', 'data' => ['order_id' => 123]]);
    });

    it(
        'keeps the existing WebhookReceiver JSON-parsing behavior for a valid signed-and-timestamped request',
        function (): void {
            $secret = 'my-secret';
            $data = ['event' => 'payment.confirmed', 'data' => ['amount' => 9999]];
            $body = json_encode($data);
            $timestamp = time();

            $signature = WebhookSignature::sign($body, $secret, $timestamp);

            $request = new Request(
                server: [
                    'HTTP_X_WEBHOOK_SIGNATURE' => $signature,
                    'HTTP_X_WEBHOOK_TIMESTAMP' => (string) $timestamp,
                ],
                body: $body,
            );

            $receiver = makeReceiverWithTolerance(300);
            $result = $receiver->receive($request, $secret);

            expect($result)->toBe($data);
        },
    );
});
