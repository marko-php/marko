<?php

declare(strict_types=1);

use Marko\ErrorsAdvanced\RequestDataCollector;

describe('RequestDataCollector', function () {
    it('collects request method', function () {
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['method'])->toBe('POST');
    });

    it('collects request URI', function () {
        $_SERVER['REQUEST_URI'] = '/api/users?page=1';

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['uri'])->toBe('/api/users?page=1');
    });

    it('collects headers', function () {
        $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['headers'])->toBeArray()
            ->and($data['headers']['Content-Type'])->toBe('application/json')
            ->and($data['headers']['Accept'])->toBe('text/html');
    });

    it('collects query parameters', function () {
        $_GET = ['page' => '1', 'sort' => 'name'];

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['query'])->toBeArray()
            ->and($data['query']['page'])->toBe('1')
            ->and($data['query']['sort'])->toBe('name');
    });

    it('collects POST data', function () {
        $_POST = ['username' => 'john', 'email' => 'john@example.com'];

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['post'])->toBeArray()
            ->and($data['post']['username'])->toBe('john')
            ->and($data['post']['email'])->toBe('john@example.com');
    });

    it('masks sensitive fields like password', function () {
        $_POST = ['username' => 'john', 'password' => 'secret123'];

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['post']['username'])->toBe('john')
            ->and($data['post']['password'])->toBe('********');
    });

    it('masks authorization headers', function () {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer token123';
        $_SERVER['HTTP_ACCEPT'] = 'text/html';

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['headers']['Authorization'])->toBe('********')
            ->and($data['headers']['Accept'])->toBe('text/html');
    });

    it('masks API key fields', function () {
        $_POST = ['username' => 'john', 'api_key' => 'secret-key-123'];
        $_GET = ['apiKey' => 'query-key-456'];

        $collector = new RequestDataCollector();
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
        $_SERVER['SERVER_SOFTWARE'] = 'Apache/2.4.41';
        $_SERVER['SERVER_NAME'] = 'example.com';

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['server'])->toBeArray()
            ->and($data['server']['software'])->toBe('Apache/2.4.41')
            ->and($data['server']['name'])->toBe('example.com');
    });

    it('masks token fields', function () {
        $_POST = ['username' => 'john', 'token' => 'secret-token-123'];
        $_GET = ['access_token' => 'query-token-456', 'refresh_token' => 'refresh-789'];

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['post']['username'])->toBe('john')
            ->and($data['post']['token'])->toBe('********')
            ->and($data['query']['access_token'])->toBe('********')
            ->and($data['query']['refresh_token'])->toBe('********');
    });

    it('masks cookies', function () {
        $_COOKIE = ['session_id' => 'abc123', 'user_token' => 'secret'];

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['cookies'])->toBeArray()
            ->and($data['cookies']['session_id'])->toBe('********')
            ->and($data['cookies']['user_token'])->toBe('********');
    });

    it('handles empty request data', function () {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        unset($_SERVER['REQUEST_METHOD']);
        unset($_SERVER['REQUEST_URI']);

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['method'])->toBe('CLI')
            ->and($data['uri'])->toBe('')
            ->and($data['query'])->toBe([])
            ->and($data['post'])->toBe([])
            ->and($data['cookies'])->toBe([]);
    });

    it('handles nested data', function () {
        $_POST = [
            'user' => [
                'name' => 'john',
                'password' => 'secret123',
                'credentials' => [
                    'api_key' => 'nested-key',
                ],
            ],
        ];

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['post']['user']['name'])->toBe('john')
            ->and($data['post']['user']['password'])->toBe('********')
            ->and($data['post']['user']['credentials']['api_key'])->toBe('********');
    });

    it('preserves non-sensitive data', function () {
        $_GET = ['page' => '5', 'sort' => 'name', 'filter' => 'active'];
        $_POST = ['title' => 'Hello World', 'content' => 'Some content', 'category_id' => '42'];

        $collector = new RequestDataCollector();
        $data = $collector->collect();

        expect($data['query']['page'])->toBe('5')
            ->and($data['query']['sort'])->toBe('name')
            ->and($data['query']['filter'])->toBe('active')
            ->and($data['post']['title'])->toBe('Hello World')
            ->and($data['post']['content'])->toBe('Some content')
            ->and($data['post']['category_id'])->toBe('42');
    });
});
