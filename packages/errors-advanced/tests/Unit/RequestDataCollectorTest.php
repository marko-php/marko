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
});
