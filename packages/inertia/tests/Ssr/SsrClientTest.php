<?php

declare(strict_types=1);

use Marko\Inertia\Exceptions\InertiaConfigurationException;
use Marko\Inertia\Ssr\CurlSsrTransport;
use Marko\Inertia\Ssr\SsrClient;
use Marko\Inertia\Ssr\SsrTransportInterface;
use Marko\Testing\Fake\FakeConfigRepository;

function createSsrClient(SsrTransportInterface $transport, array $config = []): SsrClient
{
    return new SsrClient(new FakeConfigRepository(array_merge([
        'inertia.ssr.url' => 'http://localhost:13714/render',
    ], $config)), $transport);
}

test('ssr client posts page json and returns head and body', function () {
    $transport = new FakeSsrTransport(json_encode([
        'head' => '<title>Dashboard</title>',
        'body' => '<div>Rendered</div>',
    ], JSON_THROW_ON_ERROR));

    $client = createSsrClient($transport);
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
    $client = createSsrClient(new FakeSsrTransport($payload));

    expect($client->render(['component' => 'Dashboard']))->toBeNull();
})->with([
    'transport failure' => [null],
    'invalid json' => ['not-json'],
    'error response' => ['{"error":"Unknown page"}'],
    'missing body' => ['{"head":"<title>Dashboard</title>"}'],
    'empty body' => ['{"head":"<title>Dashboard</title>","body":""}'],
]);

test('ssr client throws a loud exception when ssr url config is missing', function () {
    $client = createSsrClient(new FakeSsrTransport(null), ['inertia.ssr.url' => '']);

    expect(fn () => $client->render(['component' => 'Dashboard']))
        ->toThrow(
            InertiaConfigurationException::class,
            'Inertia configuration key "inertia.ssr.url" must not be empty.',
        );
});

test('curl ssr transport returns null when curl extension is unavailable', function () {
    expect((new CurlSsrTransport())->post('http://localhost:13714/render', '{}'))->toBeNull();
})->skip(
    function_exists('curl_init'),
    'The curl extension is available in this environment.',
);

class FakeSsrTransport implements SsrTransportInterface
{
    public ?string $url = null;

    public ?string $body = null;

    public function __construct(
        private readonly ?string $response,
    ) {}

    public function post(
        string $url,
        string $body,
    ): ?string {
        $this->url = $url;
        $this->body = $body;

        return $this->response;
    }
}
