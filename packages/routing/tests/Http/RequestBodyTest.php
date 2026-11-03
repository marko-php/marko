<?php

declare(strict_types=1);

use Marko\Core\Exceptions\HttpExceptionInterface;
use Marko\Routing\Exceptions\RequestBodyTooLargeException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\RequestBody;

function countingBody(
    string $contents,
    int &$reads,
): RequestBody {
    return new RequestBody(function () use ($contents, &$reads): string {
        $reads++;

        return $contents;
    });
}

describe('RequestBody', function (): void {
    it('does not read the body until its contents are accessed', function (): void {
        $reads = 0;
        $body = countingBody('payload', $reads);

        expect($reads)->toBe(0)
            ->and($body->contents())->toBe('payload')
            ->and($reads)->toBe(1);
    });

    it('reads the body only once across repeated access', function (): void {
        $reads = 0;
        $body = countingBody('payload', $reads);

        $body->contents();
        $body->contents();

        expect($reads)->toBe(1);
    });

    it('wraps an already-known string', function (): void {
        expect(RequestBody::fromString('known')->contents())->toBe('known');
    });

    it('reads a stream lazily on first access', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'marko-body-');
        file_put_contents($path, 'before');

        $body = RequestBody::fromStream($path);
        file_put_contents($path, 'after');

        try {
            expect($body->contents())->toBe('after');
        } finally {
            unlink($path);
        }
    });

    it('throws a 413 instead of reading a body whose declared length exceeds the limit', function (): void {
        $body = RequestBody::fromStream('/path/that/must/never/be/opened', declaredLength: 2048, maxLength: 1024);

        expect(fn () => $body->contents())->toThrow(RequestBodyTooLargeException::class);

        try {
            $body->contents();
        } catch (RequestBodyTooLargeException $e) {
            expect($e)->toBeInstanceOf(HttpExceptionInterface::class)
                ->and($e->getStatusCode())->toBe(413)
                ->and($e->getContext())->toContain('2048')
                ->and($e->getContext())->toContain('1024');
        }
    });

    it('reads a body within the limit, and treats a limit of 0 as unlimited', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'marko-body-');
        file_put_contents($path, 'abc');

        try {
            expect(RequestBody::fromStream($path, declaredLength: 3, maxLength: 3)->contents())->toBe('abc')
                ->and(RequestBody::fromStream($path, declaredLength: 4096, maxLength: 0)->contents())->toBe('abc');
        } finally {
            unlink($path);
        }
    });

    it('reports whether the declared length exceeds the limit without reading', function (): void {
        expect(RequestBody::fromStream('/never', declaredLength: 10, maxLength: 5)->exceedsLimit())->toBeTrue()
            ->and(RequestBody::fromStream('/never', declaredLength: 5, maxLength: 5)->exceedsLimit())->toBeFalse()
            ->and(RequestBody::fromStream('/never', declaredLength: 10, maxLength: 0)->exceedsLimit())->toBeFalse()
            ->and(RequestBody::fromString('x')->exceedsLimit())->toBeFalse();
    });
});

describe('Request with a lazy body', function (): void {
    it('does not read the body when a JSON request is built', function (): void {
        $reads = 0;

        new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            body: countingBody('{"a":1}', $reads),
        );

        expect($reads)->toBe(0);
    });

    it('reads and decodes the body on first json() access, once', function (): void {
        $reads = 0;
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            body: countingBody('{"user":{"email":"a@b.c"}}', $reads),
        );

        expect($request->json('user.email'))->toBe('a@b.c')
            ->and($request->input('user.email'))->toBe('a@b.c')
            ->and($request->body())->toBe('{"user":{"email":"a@b.c"}}')
            ->and($reads)->toBe(1);
    });

    it('never reads the body of a form or multipart request for post(), input() or files()', function (): void {
        $reads = 0;
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'multipart/form-data; boundary=x'],
            post: ['name' => 'Ada'],
            body: countingBody('--x huge upload --x--', $reads),
        );

        expect($request->post('name'))->toBe('Ada')
            ->and($request->input('name'))->toBe('Ada')
            ->and($request->files())->toBe([])
            ->and($request->json())->toBe([])
            ->and($reads)->toBe(0);
    });

    it('shares the lazily read body with a request cloned by withRoute()', function (): void {
        $reads = 0;
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            body: countingBody('{"a":1}', $reads),
        );

        $routed = $request->withRoute('Controller', 'action');

        expect($routed->json('a'))->toBe(1)
            ->and($request->json('a'))->toBe(1)
            ->and($reads)->toBe(1);
    });

    it('throws a 413 from json() for an oversized JSON body instead of exhausting memory', function (): void {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'application/json'],
            body: RequestBody::fromStream('/never', declaredLength: 2048, maxLength: 1024),
        );

        expect(fn () => $request->json())->toThrow(RequestBodyTooLargeException::class)
            ->and($request->isJson())->toBeTrue();
    });

    it('answers an oversized multipart request from post() and files() without reading the body', function (): void {
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'CONTENT_TYPE' => 'multipart/form-data; boundary=x'],
            body: RequestBody::fromStream('/never', declaredLength: 2048, maxLength: 1024),
        );

        expect($request->post())->toBe([])
            ->and($request->input())->toBe([])
            ->and($request->hasFile('upload'))->toBeFalse();
    });
});

describe('Request::fromGlobals body', function (): void {
    it('does not read php://input for a POST larger than post_max_size', function (): void {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'multipart/form-data; boundary=x';
        $_SERVER['CONTENT_LENGTH'] = (string) (PHP_INT_MAX);
        $_GET = [];
        $_POST = [];
        $_FILES = [];
        $_COOKIE = [];

        try {
            $request = Request::fromGlobals();

            expect($request->post())->toBe([])
                ->and(fn () => $request->body())->toThrow(RequestBodyTooLargeException::class);
        } finally {
            unset($_SERVER['CONTENT_TYPE'], $_SERVER['CONTENT_LENGTH']);
        }
    })->skip(
        (int) ini_parse_quantity((string) ini_get('post_max_size')) <= 0,
        'post_max_size is unlimited',
    );
});
