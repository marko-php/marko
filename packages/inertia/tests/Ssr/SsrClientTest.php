<?php

declare(strict_types=1);

use Marko\Inertia\Ssr\CurlSsrTransport;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;

test('ssr client posts page json and returns head and body', function () {
    $transport = new FakeSsrTransport(json_encode([
        'head' => '<title>Dashboard</title>',
        'body' => '<div>Rendered</div>',
    ], JSON_THROW_ON_ERROR));

    $client = new SsrClient('http://localhost:13714/render', $transport);
    $result = $client->render(['component' => 'Dashboard', 'props' => ['user' => 'Marko']]);

    expect($transport->url)->toBe('http://localhost:13714/render');
    expect(json_decode($transport->body, true))->toMatchArray([
        'component' => 'Dashboard',
        'props' => ['user' => 'Marko'],
    ]);
    expect($result)->toBe([
        'head' => '<title>Dashboard</title>',
        'body' => '<div>Rendered</div>',
    ]);
});

test('ssr client returns null for transport failures and invalid payloads', function (?string $payload) {
    $client = new SsrClient('http://localhost:13714/render', new FakeSsrTransport($payload));

    expect($client->render(['component' => 'Dashboard']))->toBeNull();
})->with([
    'transport failure' => [null],
    'invalid json' => ['not-json'],
    'error response' => ['{"error":"Unknown page"}'],
    'missing body' => ['{"head":"<title>Dashboard</title>"}'],
    'empty body' => ['{"head":"<title>Dashboard</title>","body":""}'],
]);

test('curl ssr transport returns null when curl extension is unavailable', function () {
    expect((new CurlSsrTransport())->post('http://localhost:13714/render', '{}'))->toBeNull();
})->skip(
    function_exists('curl_init'),
    'The curl extension is available in this environment.',
);

final class FakeSsrTransport implements SsrTransportInterface
{
    public ?string $url = null;

    public ?string $body = null;

    public function __construct(
        private readonly ?string $response,
    ) {}

    public function post(string $url, string $body): ?string
    {
        $this->url = $url;
        $this->body = $body;

        return $this->response;
    }
}
