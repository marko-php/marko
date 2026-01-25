<?php

declare(strict_types=1);

use Marko\ErrorsAdvanced\RequestDataCollector;

describe('RequestDataCollector', function () {
    it('collects request method', function () {
        $collector = new RequestDataCollector(
            server: ['REQUEST_METHOD' => 'POST'],
        );
        $data = $collector->collect();

        expect($data['method'])->toBe('POST');
    });

    it('collects request URI', function () {
        $collector = new RequestDataCollector(
            server: ['REQUEST_URI' => '/api/users?page=1'],
        );
        $data = $collector->collect();

        expect($data['uri'])->toBe('/api/users?page=1');
    });

    it('collects headers', function () {
        $collector = new RequestDataCollector(
            server: [
                'HTTP_CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'text/html',
            ],
        );
        $data = $collector->collect();

        expect($data['headers'])->toBeArray()
            ->and($data['headers']['Content-Type'])->toBe('application/json')
            ->and($data['headers']['Accept'])->toBe('text/html');
    });

    it('collects query parameters', function () {
        $collector = new RequestDataCollector(
            get: ['page' => '1', 'sort' => 'name'],
        );
        $data = $collector->collect();

        expect($data['query'])->toBeArray()
            ->and($data['query']['page'])->toBe('1')
            ->and($data['query']['sort'])->toBe('name');
    });

    it('collects POST data', function () {
        $collector = new RequestDataCollector(
            post: ['username' => 'john', 'email' => 'john@example.com'],
        );
        $data = $collector->collect();

        expect($data['post'])->toBeArray()
            ->and($data['post']['username'])->toBe('john')
            ->and($data['post']['email'])->toBe('john@example.com');
    });

    it('masks sensitive fields like password', function () {
        $collector = new RequestDataCollector(
            post: ['username' => 'john', 'password' => 'secret123'],
        );
        $data = $collector->collect();

        expect($data['post']['username'])->toBe('john')
            ->and($data['post']['password'])->toBe('********');
    });

    it('masks authorization headers', function () {
        $collector = new RequestDataCollector(
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer token123',
                'HTTP_ACCEPT' => 'text/html',
            ],
        );
        $data = $collector->collect();

        expect($data['headers']['Authorization'])->toBe('********')
            ->and($data['headers']['Accept'])->toBe('text/html');
    });

    it('masks API key fields', function () {
        $collector = new RequestDataCollector(
            get: ['apiKey' => 'query-key-456'],
            post: ['username' => 'john', 'api_key' => 'secret-key-123'],
        );
        $data = $collector->collect();

        expect($data['post']['username'])->toBe('john')
            ->and($data['post']['api_key'])->toBe('********')
            ->and($data['query']['apiKey'])->toBe('********');
    });

    it('collects PHP version', function () {
        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['server']['php_version'])->toBe(PHP_VERSION);
    });

    it('collects server information', function () {
        $collector = new RequestDataCollector(
            server: [
                'SERVER_SOFTWARE' => 'Apache/2.4.41',
                'SERVER_NAME' => 'example.com',
            ],
        );
        $data = $collector->collect();

        expect($data['server'])->toBeArray()
            ->and($data['server']['software'])->toBe('Apache/2.4.41')
            ->and($data['server']['name'])->toBe('example.com');
    });

    it('masks token fields', function () {
        $collector = new RequestDataCollector(
            get: ['access_token' => 'query-token-456', 'refresh_token' => 'refresh-789'],
            post: ['username' => 'john', 'token' => 'secret-token-123'],
        );
        $data = $collector->collect();

        expect($data['post']['username'])->toBe('john')
            ->and($data['post']['token'])->toBe('********')
            ->and($data['query']['access_token'])->toBe('********')
            ->and($data['query']['refresh_token'])->toBe('********');
    });

    it('masks cookies', function () {
        $collector = new RequestDataCollector(
            cookie: ['session_id' => 'abc123', 'user_token' => 'secret'],
        );
        $data = $collector->collect();

        expect($data['cookies'])->toBeArray()
            ->and($data['cookies']['session_id'])->toBe('********')
            ->and($data['cookies']['user_token'])->toBe('********');
    });

    it('handles empty request data', function () {
        $collector = new RequestDataCollector(
            server: [],
            get: [],
            post: [],
            cookie: [],
        );
        $data = $collector->collect();

        expect($data['method'])->toBe('CLI')
            ->and($data['uri'])->toBe('')
            ->and($data['query'])->toBe([])
            ->and($data['post'])->toBe([])
            ->and($data['cookies'])->toBe([]);
    });

    it('handles nested data', function () {
        $collector = new RequestDataCollector(
            post: [
                'user' => [
                    'name' => 'john',
                    'password' => 'secret123',
                    'credentials' => [
                        'api_key' => 'nested-key',
                    ],
                ],
            ],
        );
        $data = $collector->collect();

        expect($data['post']['user']['name'])->toBe('john')
            ->and($data['post']['user']['password'])->toBe('********')
            ->and($data['post']['user']['credentials']['api_key'])->toBe('********');
    });

    it('preserves non-sensitive data', function () {
        $collector = new RequestDataCollector(
            get: ['page' => '5', 'sort' => 'name', 'filter' => 'active'],
            post: ['title' => 'Hello World', 'content' => 'Some content', 'category_id' => '42'],
        );
        $data = $collector->collect();

        expect($data['query']['page'])->toBe('5')
            ->and($data['query']['sort'])->toBe('name')
            ->and($data['query']['filter'])->toBe('active')
            ->and($data['post']['title'])->toBe('Hello World')
            ->and($data['post']['content'])->toBe('Some content')
            ->and($data['post']['category_id'])->toBe('42');
    });
});
