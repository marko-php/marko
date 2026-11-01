<?php

declare(strict_types=1);

use Marko\Routing\Http\Response;
use Marko\Testing\Http\TestResponse;
use PHPUnit\Framework\AssertionFailedError;

describe('toHaveStatus', function (): void {
    it('passes for the matching status', function (): void {
        expect(new TestResponse(new Response('', 201)))->toHaveStatus(201);
    });

    it('fails with the TestResponse message for a different status', function (): void {
        expect(fn () => expect(new TestResponse(new Response('boom', 500)))->toHaveStatus(200))
            ->toThrow(AssertionFailedError::class, 'Expected response status 200 but got 500.');
    });

    it('rejects a value that is not a TestResponse', function (): void {
        expect(fn () => expect(new Response())->toHaveStatus(200))
            ->toThrow(InvalidArgumentException::class, 'Expected TestResponse, got Marko\Routing\Http\Response');
    });
});

describe('toHaveJsonPath', function (): void {
    it('passes for the value at the path', function (): void {
        expect(new TestResponse(Response::json(['data' => ['status' => 'live']])))
            ->toHaveJsonPath('data.status', 'live');
    });

    it('fails with the TestResponse message for a different value', function (): void {
        expect(fn () => expect(new TestResponse(Response::json(['data' => ['status' => 'draft']])))
            ->toHaveJsonPath('data.status', 'live'))
            ->toThrow(
                AssertionFailedError::class,
                "Expected JSON path [data.status] to be 'live' but it was 'draft'.",
            );
    });

    it('rejects a value that is not a TestResponse', function (): void {
        expect(fn () => expect('{"a":1}')->toHaveJsonPath('a', 1))
            ->toThrow(InvalidArgumentException::class, 'Expected TestResponse, got string');
    });
});

describe('toHaveSession', function (): void {
    it('rejects a value that is not a TestResponse', function (): void {
        expect(fn () => expect('plan')->toHaveSession('plan'))
            ->toThrow(InvalidArgumentException::class, 'Expected TestResponse, got string');
    });
});
