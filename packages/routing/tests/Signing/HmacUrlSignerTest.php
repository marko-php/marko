<?php

declare(strict_types=1);

use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Exceptions\HttpException;
use Marko\Routing\Exceptions\InvalidSignatureException;
use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\Exceptions\UrlSigningException;
use Marko\Routing\Http\Request;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RoutingConfig;
use Marko\Routing\Signing\HmacUrlSigner;
use Marko\Routing\Signing\UrlSignerInterface;
use Marko\Routing\UrlGenerator;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

const SIGNER_TEST_KEY = 'current-key-0123456789abcdef0123456789abcdef';
const SIGNER_TEST_OLD_KEY = 'retired-key-0123456789abcdef0123456789abcdef';

/**
 * @param array<string, mixed> $config
 */
function urlSignerConfig(
    array $config = [],
): RoutingConfig {
    return new RoutingConfig(new FakeConfigRepository([
        'routing.url' => 'https://example.com',
        'routing.signing.key' => SIGNER_TEST_KEY,
        'routing.signing.previous_keys' => [],
        'routing.signing.ignored_parameters' => [],
        ...$config,
    ]));
}

/**
 * @param array<string, mixed> $config
 */
function urlSigner(
    array $config = [],
    ?FakeClock $clock = null,
): HmacUrlSigner {
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/newsletter/{id:\d+}/unsubscribe',
        controller: 'C',
        action: 'unsubscribe',
        name: 'newsletter.unsubscribe',
    ));
    $routingConfig = urlSignerConfig($config);

    return new HmacUrlSigner(
        urlGenerator: new UrlGenerator($routes, $routingConfig),
        routingConfig: $routingConfig,
        clock: $clock ?? new FakeClock('2026-10-31 12:00:00'),
    );
}

/**
 * Build the request a browser would send for $url.
 */
function signedRequest(
    string $url,
): Request {
    $parts = parse_url($url);
    $query = [];
    parse_str($parts['query'] ?? '', $query);
    $server = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '')];

    if (isset($parts['host'])) {
        $server['HTTP_HOST'] = $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
        $server['HTTPS'] = ($parts['scheme'] ?? 'http') === 'https' ? 'on' : '';
    }

    return new Request(server: $server, query: $query);
}

function withQuery(
    string $url,
    string $query,
): string {
    return str_contains($url, '?') ? "$url&$query" : "$url?$query";
}

describe('HmacUrlSigner', function (): void {
    it('implements UrlSignerInterface', function (): void {
        expect(urlSigner())->toBeInstanceOf(UrlSignerInterface::class);
    });

    it('signs a named route as an absolute URL that validates', function (): void {
        $signer = urlSigner();
        $url = $signer->signedRoute('newsletter.unsubscribe', ['id' => 7, 'list' => 'weekly']);

        expect($url)->toStartWith('https://example.com/newsletter/7/unsubscribe?list=weekly&signature=')
            ->and($signer->hasValidSignature(signedRequest($url)))->toBeTrue();

        $signer->assertValidSignature(signedRequest($url));
    });

    it('signs a relative route URL bound to the configured base URL host', function (): void {
        $signer = urlSigner();
        $url = $signer->signedRoute('newsletter.unsubscribe', ['id' => 7], absolute: false);

        expect($url)->toStartWith('/newsletter/7/unsubscribe?signature=')
            ->and($signer->hasValidSignature(signedRequest("https://example.com$url")))->toBeTrue()
            ->and($signer->hasValidSignature(signedRequest("https://evil.test$url")))->toBeFalse();
    });

    it('lets a URL generation exception propagate from signedRoute', function (): void {
        urlSigner()->signedRoute('missing.route');
    })->throws(UrlGenerationException::class);

    it('signs an arbitrary absolute URL', function (): void {
        $signer = urlSigner();
        $url = $signer->sign('https://tenant.example.org/share/abc?b=2&a=1');

        expect($url)->toStartWith('https://tenant.example.org/share/abc?b=2&a=1&signature=')
            ->and($signer->hasValidSignature(signedRequest($url)))->toBeTrue();
    });

    it('rejects a tampered parameter', function (): void {
        $signer = urlSigner();
        $url = $signer->signedRoute('newsletter.unsubscribe', ['id' => 7, 'list' => 'weekly']);

        expect($signer->hasValidSignature(signedRequest(str_replace('list=weekly', 'list=daily', $url))))->toBeFalse()
            ->and($signer->hasValidSignature(signedRequest(str_replace('/7/', '/8/', $url))))->toBeFalse()
            ->and($signer->hasValidSignature(signedRequest(withQuery($url, 'extra=1'))))->toBeFalse();
    });

    it('rejects a tampered host', function (): void {
        $signer = urlSigner();
        $url = $signer->signedRoute('newsletter.unsubscribe', ['id' => 7]);

        expect($signer->hasValidSignature(signedRequest(str_replace('example.com', 'other.example.com', $url))))
            ->toBeFalse();
    });

    it('rejects a tampered scheme', function (): void {
        $signer = urlSigner();
        $url = $signer->signedRoute('newsletter.unsubscribe', ['id' => 7]);

        expect($signer->hasValidSignature(signedRequest(str_replace('https://', 'http://', $url))))->toBeFalse();
    });

    it('treats the host case-insensitively and ignores the default port', function (): void {
        $signer = urlSigner();
        $url = $signer->signedRoute('newsletter.unsubscribe', ['id' => 7]);

        expect($signer->hasValidSignature(signedRequest(str_replace('example.com', 'EXAMPLE.com:443', $url))))
            ->toBeTrue();
    });

    it('rejects a request without a signature', function (): void {
        expect(urlSigner()->hasValidSignature(signedRequest('https://example.com/newsletter/7/unsubscribe')))
            ->toBeFalse();
    });

    it('validates reordered query parameters', function (): void {
        $signer = urlSigner();
        $url = $signer->sign('https://example.com/share?b=2&a=1');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $reordered = 'https://example.com/share?signature=' . $query['signature'] . '&a=1&b=2';

        expect($signer->hasValidSignature(signedRequest($reordered)))->toBeTrue();
    });

    it('validates an equivalent percent-encoding of the path', function (): void {
        $signer = urlSigner();
        $url = $signer->sign('https://example.com/files/a~b');

        expect($signer->hasValidSignature(signedRequest(str_replace('a~b', 'a%7Eb', $url))))->toBeTrue();
    });

    it('ignores the configured ignored parameters', function (): void {
        $signer = urlSigner(['routing.signing.ignored_parameters' => ['utm_source', 'fbclid']]);
        $url = $signer->signedRoute('newsletter.unsubscribe', ['id' => 7]);

        expect($signer->hasValidSignature(signedRequest(withQuery($url, 'utm_source=mail&fbclid=xyz'))))->toBeTrue()
            ->and($signer->hasValidSignature(signedRequest(withQuery($url, 'other=1'))))->toBeFalse();
    });

    it('signs an expiring URL that validates until it expires', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $signer = urlSigner(clock: $clock);
        $url = $signer->signedRoute(
            'newsletter.unsubscribe',
            ['id' => 7],
            expiresAt: new DateTimeImmutable('2026-10-31 13:00:00'),
        );

        expect($url)->toContain('expires=' . new DateTimeImmutable('2026-10-31 13:00:00')->getTimestamp())
            ->and($signer->hasValidSignature(signedRequest($url)))->toBeTrue();

        $clock->travel('+59 minutes');
        expect($signer->hasValidSignature(signedRequest($url)))->toBeTrue();

        $clock->travel('+1 minute');
        expect($signer->hasValidSignature(signedRequest($url)))->toBeFalse()
            ->and(fn () => $signer->assertValidSignature(signedRequest($url)))
            ->toThrow(InvalidSignatureException::class, 'This link has expired.');
    });

    it('rejects a tampered expiry', function (): void {
        $signer = urlSigner();
        $expiresAt = new DateTimeImmutable('2026-10-31 13:00:00');
        $url = $signer->signedRoute('newsletter.unsubscribe', ['id' => 7], expiresAt: $expiresAt);
        $tampered = str_replace(
            'expires=' . $expiresAt->getTimestamp(),
            'expires=' . ($expiresAt->getTimestamp() + 86400),
            $url,
        );

        expect(fn () => $signer->assertValidSignature(signedRequest($tampered)))
            ->toThrow(InvalidSignatureException::class, 'This link is invalid.');
    });

    it('says the link is invalid rather than expired when the signature is wrong', function (): void {
        $clock = new FakeClock('2026-10-31 12:00:00');
        $signer = urlSigner(clock: $clock);
        $url = $signer->signedRoute(
            'newsletter.unsubscribe',
            ['id' => 7],
            expiresAt: new DateTimeImmutable('2026-10-31 12:30:00'),
        );
        $clock->travel('+1 hour');

        $signer->assertValidSignature(signedRequest(str_replace('/7/', '/8/', $url)));
    })->throws(InvalidSignatureException::class, 'This link is invalid.');

    it('validates a link signed with a previous key', function (): void {
        $oldUrl = urlSigner(['routing.signing.key' => SIGNER_TEST_OLD_KEY])
            ->signedRoute('newsletter.unsubscribe', ['id' => 7]);
        $rotated = urlSigner(['routing.signing.previous_keys' => [SIGNER_TEST_OLD_KEY]]);

        expect($rotated->hasValidSignature(signedRequest($oldUrl)))->toBeTrue()
            ->and(urlSigner()->hasValidSignature(signedRequest($oldUrl)))->toBeFalse();
    });

    it('signs new URLs with the current key, not a previous key', function (): void {
        $rotated = urlSigner(['routing.signing.previous_keys' => [SIGNER_TEST_OLD_KEY]]);
        $newUrl = $rotated->signedRoute('newsletter.unsubscribe', ['id' => 7]);

        expect(urlSigner()->hasValidSignature(signedRequest($newUrl)))->toBeTrue()
            ->and(urlSigner(['routing.signing.key' => SIGNER_TEST_OLD_KEY])->hasValidSignature(signedRequest($newUrl)))
            ->toBeFalse();
    });

    it('throws when the signing key is empty', function (): void {
        urlSigner(['routing.signing.key' => ''])->sign('https://example.com/a');
    })->throws(UrlSigningException::class, 'URL signing key is not configured');

    it('throws when validating with an empty signing key', function (): void {
        urlSigner(['routing.signing.key' => ''])->hasValidSignature(
            signedRequest('https://example.com/a?signature=x'),
        );
    })->throws(UrlSigningException::class, 'URL signing key is not configured');

    it('throws when the signing key is too short', function (): void {
        urlSigner(['routing.signing.key' => 'short'])->sign('https://example.com/a');
    })->throws(UrlSigningException::class, 'at least 32 bytes');

    it('throws when the signing key config is missing', function (): void {
        $routingConfig = new RoutingConfig(new FakeConfigRepository(['routing.url' => 'https://example.com']));

        new HmacUrlSigner(
            urlGenerator: new UrlGenerator(new RouteCollection(), $routingConfig),
            routingConfig: $routingConfig,
            clock: new FakeClock(),
        )->sign('https://example.com/a');
    })->throws(ConfigNotFoundException::class, 'routing.signing.key');

    it('throws when a previous key is empty', function (): void {
        urlSigner(['routing.signing.previous_keys' => ['']])
            ->hasValidSignature(signedRequest('https://example.com/a?signature=x'));
    })->throws(UrlSigningException::class, 'routing.signing.previous_keys');

    it('throws when signing a URL that already carries a reserved parameter', function (): void {
        urlSigner()->sign('https://example.com/a?signature=abc');
    })->throws(UrlSigningException::class, "'signature'");

    it('throws when signing a URL with a fragment', function (): void {
        urlSigner()->sign('https://example.com/a#top');
    })->throws(UrlSigningException::class, 'fragment');

    it('throws when signing a relative URL without a base URL', function (): void {
        urlSigner(['routing.url' => ''])->sign('/a');
    })->throws(UrlSigningException::class, 'no base URL is configured');

    it('throws an InvalidSignatureException that renders as a 403', function (): void {
        try {
            urlSigner()->assertValidSignature(signedRequest('https://example.com/a?signature=nope'));
        } catch (InvalidSignatureException $exception) {
            expect($exception)->toBeInstanceOf(HttpException::class)
                ->and($exception->getStatusCode())->toBe(403)
                ->and($exception->getMessage())->toBe('This link is invalid.');

            return;
        }

        throw new RuntimeException('Expected an InvalidSignatureException.');
    });
});

describe('URL signing wiring', function (): void {
    it('binds UrlSignerInterface to HmacUrlSigner as a singleton', function (): void {
        $module = require dirname(__DIR__, 2) . '/module.php';

        expect($module['singletons'])->toMatchArray([UrlSignerInterface::class => HmacUrlSigner::class]);
    });

    it('requires marko/clock for the current time', function (): void {
        $composer = json_decode(file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);

        expect($composer['require'])->toHaveKey('marko/clock');
    });

    it('ships signing config with no key fallback', function (): void {
        $config = require dirname(__DIR__, 2) . '/config/routing.php';

        expect($config['signing'])->toHaveKeys(['key', 'previous_keys', 'ignored_parameters'])
            ->and($config['signing']['ignored_parameters'])->toBe([])
            ->and(file_get_contents(dirname(__DIR__, 2) . '/config/routing.php'))
            ->toContain("Env::string('URL_SIGNING_KEY', '')")
            ->toContain("Env::list('URL_SIGNING_PREVIOUS_KEYS', [])");
    });

    it('reads the signing settings through RoutingConfig', function (): void {
        $config = urlSignerConfig([
            'routing.signing.previous_keys' => [SIGNER_TEST_OLD_KEY],
            'routing.signing.ignored_parameters' => ['utm_source'],
        ]);

        expect($config->signingKey())->toBe(SIGNER_TEST_KEY)
            ->and($config->signingPreviousKeys())->toBe([SIGNER_TEST_OLD_KEY])
            ->and($config->signingIgnoredParameters())->toBe(['utm_source']);
    });
});
