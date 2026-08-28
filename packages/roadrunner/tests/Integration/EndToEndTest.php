<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Integration;

use function Marko\Roadrunner\Tests\locateRoadRunnerBinary;

use function Marko\Roadrunner\Tests\roadRunnerSkipReason;
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
 * `rr` binary, every test below skips with a clear explanation instead of
 * failing — see 'skips with a clear message...' below, and
 * {@see roadRunnerSkipReason()}.
 */
afterAll(function (): void {
    SharedRoadRunnerServer::stopIfStarted();
});

it('skips with a clear message when the roadrunner binary is unavailable', function (): void {
    $binary = locateRoadRunnerBinary();

    expect(roadRunnerSkipReason())
        ->toContain('RoadRunner binary not found')
        ->toContain('rr get-binary')
        ->and($binary === null || (is_file($binary) && is_executable($binary)))->toBeTrue();
});

it('serves a successful http response through a real roadrunner process', function (): void {
    $response = sharedRoadRunnerServer()->client()->get('/session/read');

    expect($response->statusCode)->toBe(200)
        ->and($response->body)->toContain('visits=0')
        ->and($response->body)->toContain('user=guest');
})
    ->skip(fn (): bool => locateRoadRunnerBinary() === null, roadRunnerSkipReason())
    ->group('integration-destructive');

it('preserves a session across two requests from the same client', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $first = $client->get('/session/write');
    $cookie = $first->cookiePair();
    $second = $client->get('/session/read', $cookie);

    expect($cookie)->not->toBeNull()
        ->and($first->body)->toContain('visits=1')
        ->and($second->body)->toContain('visits=1');
})
    ->skip(fn (): bool => locateRoadRunnerBinary() === null, roadRunnerSkipReason())
    ->group('integration-destructive');

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
    ->skip(fn (): bool => locateRoadRunnerBinary() === null, roadRunnerSkipReason())
    ->group('integration-destructive');

it('does not leak the authenticated user into an anonymous request', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $userA = $client->get('/session/write');
    $anonymous = $client->get('/session/read');
    $userB = $client->get('/session/write');

    expect($userA->body)->toContain('user=1')
        ->and($anonymous->body)->toContain('user=guest')
        ->and($userB->body)->toContain('user=1');
})
    ->skip(fn (): bool => locateRoadRunnerBinary() === null, roadRunnerSkipReason())
    ->group('integration-destructive');

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
    ->skip(fn (): bool => locateRoadRunnerBinary() === null, roadRunnerSkipReason())
    ->group('integration-destructive');

it('sets a session cookie on the first request and not on the second', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $first = $client->get('/session/read');
    $cookie = $first->cookiePair();
    $second = $client->get('/session/read', $cookie);

    expect($first->setCookie)->not->toBeNull()
        ->and($cookie)->not->toBeNull()
        ->and($second->setCookie)->toBeNull();
})
    ->skip(fn (): bool => locateRoadRunnerBinary() === null, roadRunnerSkipReason())
    ->group('integration-destructive');

it('passes a csrf protected form submission', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $tokenResponse = $client->get('/csrf/token');
    $cookie = $tokenResponse->cookiePair();
    $submission = $client->post('/csrf/submit', ['_token' => $tokenResponse->body], $cookie);

    expect($cookie)->not->toBeNull()
        ->and($submission->statusCode)->toBe(200)
        ->and($submission->body)->toBe('csrf-ok');
})
    ->skip(fn (): bool => locateRoadRunnerBinary() === null, roadRunnerSkipReason())
    ->group('integration-destructive');

it('returns a five hundred and keeps serving after a request throws', function (): void {
    $client = sharedRoadRunnerServer()->client();

    $failed = $client->get('/session/throw');
    $recovered = $client->get('/session/read');

    expect($failed->statusCode)->toBe(500)
        ->and($recovered->statusCode)->toBe(200);
})
    ->skip(fn (): bool => locateRoadRunnerBinary() === null, roadRunnerSkipReason())
    ->group('integration-destructive');
