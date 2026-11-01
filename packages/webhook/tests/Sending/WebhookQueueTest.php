<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Sending;

use Marko\Queue\PushResult;
use Marko\Testing\Fake\FakeQueue;
use Marko\Webhook\Exceptions\InvalidWebhookSecretException;
use Marko\Webhook\Jobs\DispatchWebhookJob;
use Marko\Webhook\Sending\WebhookQueue;
use Marko\Webhook\Tests\Fixtures\FakeEncryptor;
use Marko\Webhook\Value\WebhookPayload;

describe('WebhookQueue', function (): void {
    it('pushes a DispatchWebhookJob whose serialized form never contains the signing secret', function (): void {
        $queue = new FakeQueue();
        $payload = new WebhookPayload(
            url: 'https://example.com/webhook',
            event: 'order.created',
            data: ['order_id' => 42],
            secret: 'whsec-subscriber-secret',
        );

        new WebhookQueue($queue, new FakeEncryptor())->push($payload);

        $queue->assertPushed(DispatchWebhookJob::class);
        $job = $queue->pushed[0]['job'] ?? null;

        expect($job)->toBeInstanceOf(DispatchWebhookJob::class)
            ->and($job->payload->id)->toBe($payload->id)
            ->and($job->payload->url)->toBe('https://example.com/webhook')
            ->and($job->serialize())->not->toContain('whsec-subscriber-secret');
    });

    it("returns the queue's PushResult for the pushed job", function (): void {
        $queue = new FakeQueue();
        $payload = new WebhookPayload('https://example.com/webhook', 'order.created', [], 'whsec-subscriber-secret');

        $result = new WebhookQueue($queue, new FakeEncryptor())->push($payload);

        expect($result)->toBeInstanceOf(PushResult::class)
            ->and($result->pushed)->toBeTrue()
            ->and($result->id)->toBe($queue->pushed[0]['id']);
    });

    it('rejects a payload whose secret is too short before anything is queued', function (): void {
        $queue = new FakeQueue();
        $payload = new WebhookPayload('https://example.com/webhook', 'order.created', [], 'short');

        expect(fn () => new WebhookQueue($queue, new FakeEncryptor())->push($payload))
            ->toThrow(InvalidWebhookSecretException::class);

        $queue->assertNothingPushed();
    });
});
