<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Http;

use Marko\Roadrunner\Exceptions\UploadedFilesNotSupportedException;
use Marko\Roadrunner\Http\Psr7RequestBridge;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\Stream;
use Nyholm\Psr7\UploadedFile;

describe('Psr7RequestBridge', function (): void {
    it('maps the request method from the psr7 request', function (): void {
        $psr7Request = new ServerRequest('POST', 'https://example.test/users');

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->method())->toBe('POST');
    });

    it('maps the request path from the psr7 uri', function (): void {
        $psr7Request = new ServerRequest('GET', 'https://example.test/users/42');

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->path())->toBe('/users/42');
    });

    it('maps query parameters from the psr7 request', function (): void {
        $psr7Request = new ServerRequest('GET', 'https://example.test/users?page=2&sort=name');

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->query('page'))->toBe('2')
            ->and($request->query('sort'))->toBe('name');
    });

    it('maps parsed body parameters to the post array', function (): void {
        $psr7Request = (new ServerRequest('POST', 'https://example.test/users'))
            ->withParsedBody(['name' => 'Ada']);

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->post('name'))->toBe('Ada');
    });

    it('maps psr7 headers so that header lookup works', function (): void {
        $psr7Request = new ServerRequest(
            'GET',
            'https://example.test/users',
            ['X-Custom-Header' => 'custom-value'],
        );

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->header('X-Custom-Header'))->toBe('custom-value');
    });

    it('joins multi value psr7 headers into a single server entry', function (): void {
        $psr7Request = new ServerRequest(
            'GET',
            'https://example.test/users',
            ['Accept' => ['text/html', 'application/json']],
        );

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->header('Accept'))->toBe('text/html, application/json');
    });

    it('includes the query string in the request uri server key', function (): void {
        $psr7Request = new ServerRequest('GET', 'https://example.test/users?page=2&sort=name');

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->server('REQUEST_URI'))->toBe('/users?page=2&sort=name');
    });

    it('maps the remote address so that ip lookup works', function (): void {
        $psr7Request = new ServerRequest(
            'GET',
            'https://example.test/users',
            [],
            null,
            '1.1',
            ['REMOTE_ADDR' => '203.0.113.7'],
        );

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->ip())->toBe('203.0.113.7');
    });

    it('maps content type and content length as bare server keys', function (): void {
        $psr7Request = new ServerRequest(
            'POST',
            'https://example.test/users',
            [
                'Content-Type' => 'application/json',
                'Content-Length' => '42',
            ],
        );

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->server('CONTENT_TYPE'))->toBe('application/json')
            ->and($request->server('CONTENT_LENGTH'))->toBe('42');
    });

    it('sets the https server key only for https requests', function (): void {
        $httpsRequest = new ServerRequest('GET', 'https://example.test/users');
        $httpRequest = new ServerRequest('GET', 'http://example.test/users');

        $bridge = new Psr7RequestBridge();

        expect($bridge->bridge($httpsRequest)->server('HTTPS'))->toBe('on')
            ->and($bridge->bridge($httpRequest)->server('HTTPS'))->toBeNull();
    });

    it('maps cookies from the psr7 request', function (): void {
        $psr7Request = (new ServerRequest('GET', 'https://example.test/users'))
            ->withCookieParams(['session' => 'abc123']);

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->cookie('session'))->toBe('abc123');
    });

    it('parses a form encoded body for put patch and delete requests', function (): void {
        $psr7Request = new ServerRequest(
            'PUT',
            'https://example.test/users/1',
            ['Content-Type' => 'application/x-www-form-urlencoded'],
            'name=Ada&role=admin',
        );

        $request = (new Psr7RequestBridge())->bridge($psr7Request);

        expect($request->post('name'))->toBe('Ada')
            ->and($request->post('role'))->toBe('admin');
    });

    it('throws a loud error when the psr7 request carries uploaded files', function (): void {
        $uploadedFile = new UploadedFile(
            Stream::create('file contents'),
            13,
            UPLOAD_ERR_OK,
            'avatar.png',
            'image/png',
        );

        $psr7Request = (new ServerRequest('POST', 'https://example.test/users'))
            ->withUploadedFiles(['avatar' => $uploadedFile]);

        $bridge = new Psr7RequestBridge();

        expect(fn () => $bridge->bridge($psr7Request))
            ->toThrow(UploadedFilesNotSupportedException::class);
    });
});
