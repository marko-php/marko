<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\UrlGenerationException;
use Marko\Routing\Http\Request;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;
use Marko\Routing\RoutingConfig;
use Marko\Routing\Signing\HmacUrlSigner;
use Marko\Routing\UrlGenerator;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\Attributes\TemplateFunctions;
use Marko\View\Functions\SignedRouteFunctionProvider;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

function signedRouteSigner(): HmacUrlSigner
{
    $routes = new RouteCollection();
    $routes->add(new RouteDefinition(
        method: 'GET',
        path: '/verify/{id:\d+}',
        controller: 'C',
        action: 'verify',
        name: 'verification.verify',
    ));
    $routingConfig = new RoutingConfig(new FakeConfigRepository([
        'routing.url' => 'https://example.com',
        'routing.signing.key' => 'template-key-0123456789abcdef0123456789abcdef',
        'routing.signing.previous_keys' => [],
        'routing.signing.ignored_parameters' => [],
    ]));

    return new HmacUrlSigner(
        urlGenerator: new UrlGenerator($routes, $routingConfig),
        routingConfig: $routingConfig,
        clock: new FakeClock('2026-10-31 12:00:00'),
    );
}

describe('SignedRouteFunctionProvider', function (): void {
    it('is a discoverable template function provider', function (): void {
        $reflection = new ReflectionClass(SignedRouteFunctionProvider::class);

        expect($reflection->implementsInterface(TemplateFunctionProviderInterface::class))->toBeTrue()
            ->and($reflection->getAttributes(TemplateFunctions::class))->toHaveCount(1);
    });

    it('provides one escaped signedRoute function', function (): void {
        $functions = new SignedRouteFunctionProvider(signedRouteSigner())->functions();

        expect($functions)->toHaveCount(1)
            ->and($functions[0])->toBeInstanceOf(TemplateFunction::class)
            ->and($functions[0]->name)->toBe('signedRoute')
            ->and($functions[0]->isSafeHtml)->toBeFalse();
    });

    it('builds signed URLs through the URL signer', function (): void {
        $signer = signedRouteSigner();
        $signedRoute = new SignedRouteFunctionProvider($signer)->functions()[0]->callable;

        $url = $signedRoute(
            'verification.verify',
            ['id' => 5],
            expiresAt: new DateTimeImmutable('2026-10-31 13:00:00'),
        );
        $parts = parse_url($url);
        parse_str($parts['query'], $query);
        $request = new Request(
            server: ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on', 'REQUEST_URI' => $parts['path'] . '?' . $parts['query']],
            query: $query,
        );

        expect($url)->toStartWith('https://example.com/verify/5?expires=')
            ->and($signer->hasValidSignature($request))->toBeTrue()
            ->and($signedRoute('verification.verify', ['id' => 5], absolute: false))
            ->toStartWith('/verify/5?signature=');
    });

    it('lets a URL generation exception propagate', function (): void {
        $signedRoute = new SignedRouteFunctionProvider(signedRouteSigner())->functions()[0]->callable;

        $signedRoute('verification.missing');
    })->throws(UrlGenerationException::class, "No route named 'verification.missing'");
});
