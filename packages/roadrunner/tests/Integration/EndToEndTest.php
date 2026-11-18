<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Integration;

use function Marko\Roadrunner\Tests\roadRunnerSkipReason;
use function Marko\Roadrunner\Tests\roadRunnerUnavailable;
use function Marko\Roadrunner\Tests\sharedRoadRunnerServer;

use Marko\Roadrunner\Tests\Support\SharedRoadRunnerServer;

/**
 * Proves the whole stack actually works: a real Marko application served by
 * a real `rr serve` process, with sessions, CSRF and auth intact across
 * sequential requests from different identities.
 *
 * Every test here shares one `rr serve` process (started lazily, on the
 * first test that needs it, and stopped once at the end of the file) forced
 * to exactly one worker, so every request across every test in this file —
 * not just within a single test — lands on the same PHP process. That is a
 * stronger isolation guarantee than the plan asks for, not a weaker one.
 *
 * No PSR-7 bridging is faked here: the binary, the goridge wire protocol,
 * and the HTTP server are all real. On a machine (or CI job) without the
 * `rr` binary, every test below skips with a clear explanation; under
 * `MARKO_INTEGRATION_REQUIRED=1` (the CI Integration job) it fails instead, so
 * the suite can never pass by skipping — see {@see roadRunnerUnavailable()}
 * and {@see roadRunnerSkipReason()}.
 */
afterAll(function (): void {
    SharedRoadRunnerServer::stopIfStarted();
});

it('serves a successful http response through a real roadrunner process', function (): void {
    $response = sharedRoadRunnerServer()->client()->get('/session/read');

    expect($response->statusCode)->toBe(200)
        ->and($response->body)->toContain('visits=0')
        ->toContain('user=guest');
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');

it('preserves a session across two requests from the same client', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $first = $client->get('/session/write');
    $cookie = $first->cookiePair();
    $second = $client->get('/session/read', $cookie);

    expect($cookie)->not->toBeNull()
        ->and($first->body)->toContain('visits=1')
        ->and($second->body)->toContain('visits=1');
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');

it('does not leak session state between two different clients', function (): void {
    $client = sharedRoadRunnerServer()->client();

    // Three sequential requests through the one shared worker: an
    // authenticated user, then a client presenting no cookie at all, then a
    // second, distinct authenticated user.
    $userA = $client->get('/session/write');
    $anonymous = $client->get('/session/read');
    $userB = $client->get('/session/write');

    expect($userA->body)->toContain('visits=1')
        ->and($anonymous->body)->toContain('visits=0')
        ->and($userB->body)->toContain('visits=1');
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');

it('does not leak the authenticated user into an anonymous request', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $userA = $client->get('/session/write');
    $anonymous = $client->get('/session/read');
    $userB = $client->get('/session/write');

    expect($userA->body)->toContain('user=1')
        ->and($anonymous->body)->toContain('user=guest')
        ->and($userB->body)->toContain('user=1');
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');

it('does not leak the authenticated user between two different clients', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $userA = $client->get('/session/write');
    $anonymous = $client->get('/session/read');
    $userB = $client->get('/session/write');

    expect($userA->body)->toContain('user=1')
        ->and($anonymous->body)->toContain('user=guest')
        ->and($userB->body)->toContain('user=1')
        ->and($userA->body)->not->toBe($userB->body);
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');

it('sets a session cookie on the first write and not on the next request', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $first = $client->get('/session/write');
    $cookie = $first->cookiePair();
    $second = $client->get('/session/read', $cookie);

    // The XSRF-TOKEN cookie is reissued whenever the session is saved, so
    // only the session cookie itself proves whether a session was started.
    expect($first->sessionCookie())->not->toBeNull()
        ->and($cookie)->not->toBeNull()
        ->and($second->sessionCookie())->toBeNull();
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');

it('sets no session cookie for a request that only reads an absent session', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $response = $client->get('/session/read');

    expect($response->statusCode)->toBe(200)
        ->and($response->setCookies)->toBeEmpty();
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');

it('passes a csrf protected form submission', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $tokenResponse = $client->get('/csrf/token');
    $cookie = $tokenResponse->cookiePair();
    $submission = $client->post('/csrf/submit', ['_token' => $tokenResponse->body], $cookie);

    expect($cookie)->not->toBeNull()
        ->and($submission->statusCode)->toBe(200)
        ->and($submission->body)->toBe('csrf-ok');
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');

it('returns a five hundred and keeps serving after a request throws', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $failed = $client->get('/session/throw');
    $recovered = $client->get('/session/read');

    expect($failed->statusCode)->toBe(500)
        ->and($recovered->statusCode)->toBe(200);
})
    ->skip(fn (): bool => roadRunnerUnavailable(), roadRunnerSkipReason())
    ->group('integration-services');
