<?php

declare(strict_types=1);

use Marko\Auth\Command\ClearTokensCommand;
use Marko\Auth\Contracts\RememberTokenStorageInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

it('has correct command name auth:clear-tokens', function (): void {
    $reflection = new ReflectionClass(ClearTokensCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();
    expect($command->name)->toBe('auth:clear-tokens');
});

it('has description', function (): void {
    $reflection = new ReflectionClass(ClearTokensCommand::class);
    $attributes = $reflection->getAttributes(Command::class);
    $command = $attributes[0]->newInstance();

    expect($command->description)->not->toBeEmpty();
});

it('clears expired tokens', function (): void {
    $storage = new class () implements RememberTokenStorageInterface
    {
        public int $expiredCleared = 0;

        public int $allCleared = 0;

        public function clearExpiredTokens(): int
        {
            $this->expiredCleared = 3;

            return $this->expiredCleared;
        }

        public function clearAllTokens(): int
        {
            $this->allCleared = 5;

            return $this->allCleared;
        }
    };

    $command = new ClearTokensCommand($storage);
    $input = new Input(['marko', 'auth:clear-tokens']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    expect($result)->toBe(0)
        ->and($storage->expiredCleared)->toBe(3);
});

it('reports number of tokens cleared', function (): void {
    $storage = new class () implements RememberTokenStorageInterface
    {
        public function clearExpiredTokens(): int
        {
            return 5;
        }

        public function clearAllTokens(): int
        {
            return 10;
        }
    };

    $command = new ClearTokensCommand($storage);
    $input = new Input(['marko', 'auth:clear-tokens']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $command->execute($input, $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($content)->toContain('5')
        ->and($content)->toContain('token');
});

it('handles no expired tokens gracefully', function (): void {
    $storage = new class () implements RememberTokenStorageInterface
    {
        public function clearExpiredTokens(): int
        {
            return 0;
        }

        public function clearAllTokens(): int
        {
            return 0;
        }
    };

    $command = new ClearTokensCommand($storage);
    $input = new Input(['marko', 'auth:clear-tokens']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($result)->toBe(0)
        ->and($content)->toContain('No expired tokens');
});

it('supports --force flag for all tokens', function (): void {
    $storage = new class () implements RememberTokenStorageInterface
    {
        public bool $clearAllCalled = false;

        public bool $clearExpiredCalled = false;

        public function clearExpiredTokens(): int
        {
            $this->clearExpiredCalled = true;

            return 2;
        }

        public function clearAllTokens(): int
        {
            $this->clearAllCalled = true;

            return 10;
        }
    };

    $command = new ClearTokensCommand($storage);
    $input = new Input(['marko', 'auth:clear-tokens', '--force']);
    $stream = fopen('php://memory', 'r+');
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $content = stream_get_contents($stream);

    expect($result)->toBe(0)
        ->and($storage->clearAllCalled)->toBeTrue()
        ->and($storage->clearExpiredCalled)->toBeFalse()
        ->and($content)->toContain('10')
        ->and($content)->toContain('all');
});
