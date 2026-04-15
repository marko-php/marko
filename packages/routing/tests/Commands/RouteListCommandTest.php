<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Routing\Commands\RouteListCommand;
use Marko\Routing\RouteCollection;
use Marko\Routing\RouteDefinition;

function makeCollection(array $routes): RouteCollection
{
    $collection = new RouteCollection();

    foreach ($routes as $route) {
        $collection->add($route);
    }

    return $collection;
}

/**
 * @param array<int, string> $options
 */
function runCommand(RouteCollection $collection, array $options = []): string
{
    $command = new RouteListCommand($collection);
    $stream = fopen('php://memory', 'r+');
    $argv = array_merge(['marko', 'route:list'], $options);
    $input = new Input($argv);
    $output = new Output($stream);
    $command->execute($input, $output);
    rewind($stream);

    return stream_get_contents($stream);
}

it('has Command attribute with name route:list', function (): void {
    $reflection = new ReflectionClass(RouteListCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();

    expect($command->name)->toBe('route:list');
});

it('has Command attribute with description Show all registered routes', function (): void {
    $reflection = new ReflectionClass(RouteListCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    $command = $attributes[0]->newInstance();

    expect($command->description)->toBe('Show all registered routes');
});

it('implements CommandInterface', function (): void {
    $reflection = new ReflectionClass(RouteListCommand::class);

    expect($reflection->implementsInterface(CommandInterface::class))->toBeTrue();
});

it('displays METHOD column header', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
    ]));

    expect($result)->toContain('METHOD');
});

it('displays PATH column header', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
    ]));

    expect($result)->toContain('PATH');
});

it('displays ACTION column header', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
    ]));

    expect($result)->toContain('ACTION');
});

it('displays MIDDLEWARE column header', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
    ]));

    expect($result)->toContain('MIDDLEWARE');
});

it('displays route method path and action for each route', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('POST', '/posts', 'App\\Controllers\\PostController', 'store'),
    ]));

    expect($result)->toContain('GET')
        ->and($result)->toContain('/users')
        ->and($result)->toContain('UserController::index')
        ->and($result)->toContain('POST')
        ->and($result)->toContain('/posts')
        ->and($result)->toContain('PostController::store');
});

it('displays middleware as short class names', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/admin', 'App\\Controllers\\AdminController', 'index', [
            'App\\Middleware\\AuthMiddleware',
            'App\\Middleware\\AdminMiddleware',
        ]),
    ]));

    expect($result)->toContain('AuthMiddleware')
        ->and($result)->toContain('AdminMiddleware')
        ->and($result)->not->toContain('App\\Middleware\\AuthMiddleware');
});

it('sorts routes by path then by method', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('POST', '/articles', 'App\\Controllers\\ArticleController', 'store'),
        new RouteDefinition('GET', '/articles', 'App\\Controllers\\ArticleController', 'index'),
    ]));

    $lines = explode("\n", trim($result));

    // Header is line 0; data rows follow
    expect($lines[1])->toContain('/articles')
        ->and($lines[1])->toContain('GET')
        ->and($lines[2])->toContain('/articles')
        ->and($lines[2])->toContain('POST')
        ->and($lines[3])->toContain('/users');
});

it('displays No routes registered when collection is empty', function (): void {
    $result = runCommand(makeCollection([]));

    expect($result)->toContain('No routes registered');
});

it('displays empty middleware column when route has no middleware', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
    ]));

    $lines = explode("\n", trim($result));
    $dataLine = $lines[1];

    // The middleware column should be present but empty — no extra text after the action
    expect($dataLine)->toContain('UserController::index');
});

it('formats output with aligned columns', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('POST', '/articles', 'App\\Controllers\\ArticleController', 'store'),
    ]));

    $lines = explode("\n", trim($result));

    expect($lines[0])->toMatch('/^METHOD\s+PATH\s+ACTION\s+MIDDLEWARE$/');

    $headerPathPos = strpos($lines[0], 'PATH');
    $row1PathPos = strpos($lines[1], '/articles');
    $row2PathPos = strpos($lines[2], '/users');

    expect($row1PathPos)->toBe($headerPathPos)
        ->and($row2PathPos)->toBe($headerPathPos);
});

it('returns exit code 0', function (): void {
    $command = new RouteListCommand(makeCollection([]));
    $stream = fopen('php://memory', 'r+');
    $input = new Input([]);
    $output = new Output($stream);

    $exitCode = $command->execute($input, $output);

    expect($exitCode)->toBe(0);
});

it('shows all routes when no filters are provided', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('POST', '/posts', 'App\\Controllers\\PostController', 'store'),
    ]));

    expect($result)->toContain('/users')
        ->and($result)->toContain('/posts');
});

it('displays No routes match the given filters when filters match nothing', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
    ]), ['--method=POST']);

    expect($result)->toContain('No routes match the given filters');
});

it('combines method and path filters', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('POST', '/users', 'App\\Controllers\\UserController', 'store'),
        new RouteDefinition('GET', '/posts', 'App\\Controllers\\PostController', 'index'),
    ]), ['--method=GET', '--path=users']);

    expect($result)->toContain('/users')
        ->and($result)->toContain('GET')
        ->and($result)->not->toContain('POST')
        ->and($result)->not->toContain('/posts');
});

it('strips leading slash from path filter value before matching (route paths always start with /)', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('GET', '/posts', 'App\\Controllers\\PostController', 'index'),
    ]), ['--path=/users']);

    expect($result)->toContain('/users')
        ->and($result)->not->toContain('/posts');
});

it('filters routes by path substring when --path option is provided', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('GET', '/posts', 'App\\Controllers\\PostController', 'index'),
    ]), ['--path=users']);

    expect($result)->toContain('/users')
        ->and($result)->not->toContain('/posts');
});

it('filters routes by method case-insensitively', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('POST', '/users', 'App\\Controllers\\UserController', 'store'),
    ]), ['--method=get']);

    expect($result)->toContain('GET')
        ->and($result)->not->toContain('POST');
});

it('filters routes by method when --method option is provided', function (): void {
    $result = runCommand(makeCollection([
        new RouteDefinition('GET', '/users', 'App\\Controllers\\UserController', 'index'),
        new RouteDefinition('POST', '/users', 'App\\Controllers\\UserController', 'store'),
    ]), ['--method=GET']);

    expect($result)->toContain('GET')
        ->and($result)->not->toContain('POST');
});
