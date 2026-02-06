<?php

declare(strict_types=1);

use Marko\AdminApi\ApiResponse;
use Marko\Routing\Http\Response;

it('creates ApiResponse helper with success method returning data and meta', function (): void {
    $data = ['id' => 1, 'name' => 'Test'];
    $meta = ['version' => '1.0'];

    $response = ApiResponse::success(
        data: $data,
        meta: $meta,
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers())->toHaveKey('Content-Type')
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta')
        ->and($body['data'])->toBe($data)
        ->and($body['meta'])->toBe($meta);
});

it('creates ApiResponse helper with error method returning errors array and status code', function (): void {
    $errors = [
        ['field' => 'email', 'message' => 'Email is required'],
        ['field' => 'name', 'message' => 'Name is required'],
    ];

    $response = ApiResponse::error(
        errors: $errors,
        statusCode: 422,
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(422)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('errors')
        ->and($body['errors'])->toBe($errors);
});

it('creates ApiResponse helper with paginated method including pagination meta', function (): void {
    $items = [
        ['id' => 1, 'name' => 'First'],
        ['id' => 2, 'name' => 'Second'],
    ];

    $response = ApiResponse::paginated(
        data: $items,
        page: 2,
        perPage: 10,
        total: 25,
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(200)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('data')
        ->and($body)->toHaveKey('meta')
        ->and($body['data'])->toBe($items)
        ->and($body['meta']['page'])->toBe(2)
        ->and($body['meta']['per_page'])->toBe(10)
        ->and($body['meta']['total'])->toBe(25)
        ->and($body['meta']['total_pages'])->toBe(3);
});

it('creates ApiResponse helper with notFound method returning 404', function (): void {
    $response = ApiResponse::notFound(
        message: 'Resource not found',
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(404)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('errors')
        ->and($body['errors'])->toBeArray()
        ->and($body['errors'][0]['message'])->toBe('Resource not found');
});

it('creates ApiResponse helper with forbidden method returning 403', function (): void {
    $response = ApiResponse::forbidden(
        message: 'Access denied',
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(403)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('errors')
        ->and($body['errors'])->toBeArray()
        ->and($body['errors'][0]['message'])->toBe('Access denied');
});

it('creates ApiResponse helper with unauthorized method returning 401', function (): void {
    $response = ApiResponse::unauthorized(
        message: 'Authentication required',
    );

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(401)
        ->and($response->headers()['Content-Type'])->toBe('application/json');

    $body = json_decode($response->body(), true);

    expect($body)->toHaveKey('errors')
        ->and($body['errors'])->toBeArray()
        ->and($body['errors'][0]['message'])->toBe('Authentication required');
});
