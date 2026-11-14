<?php

declare(strict_types=1);

namespace Marko\Webhook\Tests\Value;

use Marko\Webhook\Value\WebhookResponse;

describe('WebhookResponse', function (): void {
    it('treats 408, 429 and 5xx responses as retryable', function (int $status): void {
        $response = new WebhookResponse(statusCode: $status, body: '', successful: false);

        expect($response->isRetryable())->toBeTrue();
    })->with([408, 429, 500, 502, 503, 504]);

    it('treats successful, 3xx and other 4xx responses as not retryable', function (int $status): void {
        $response = new WebhookResponse(
            statusCode: $status,
            body: '',
            successful: $status >= 200
            && $status < 300,
        );

        expect($response->isRetryable())->toBeFalse();
    })->with([200, 204, 301, 304, 400, 401, 403, 404, 410, 422]);
});
