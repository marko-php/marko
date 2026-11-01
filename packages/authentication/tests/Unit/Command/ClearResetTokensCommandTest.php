<?php

declare(strict_types=1);

use Marko\Authentication\Command\ClearResetTokensCommand;
use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Tests\Fixtures\InMemoryPasswordResetTokenRepository;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeConfigRepository;

/**
 * @return array{int, string} The exit code and what the command wrote
 */
function runClearResetTokens(
    ClearResetTokensCommand $command,
): array {
    $stream = fopen('php://memory', 'r+');
    $result = $command->execute(new Input(['marko', 'auth:clear-reset-tokens']), new Output($stream));
    rewind($stream);

    return [$result, (string) stream_get_contents($stream)];
}

function clearResetTokensCommand(
    InMemoryPasswordResetTokenRepository $repository,
    FakeClock $clock,
): ClearResetTokensCommand {
    return new ClearResetTokensCommand(
        passwordResetTokenRepository: $repository,
        authConfig: new AuthConfig(new FakeConfigRepository([
            'authentication.password_reset.expire_seconds' => 3600,
        ])),
        clock: $clock,
    );
}

it('is named auth:clear-reset-tokens', function (): void {
    $command = new ReflectionClass(ClearResetTokensCommand::class)->getAttributes(Command::class)[0]->newInstance();

    expect($command->name)->toBe('auth:clear-reset-tokens')
        ->and($command->description)->not->toBeEmpty();
});

it('deletes tokens older than expire_seconds and keeps newer ones', function (): void {
    $clock = new FakeClock('2026-06-01 12:00:00');
    $repository = new InMemoryPasswordResetTokenRepository();
    $repository->store(1, 'old', $clock->now()->modify('-2 hours'));
    $repository->store(2, 'older', $clock->now()->modify('-3601 seconds'));
    $repository->store(3, 'fresh', $clock->now()->modify('-10 minutes'));

    [$result, $content] = runClearResetTokens(clearResetTokensCommand($repository, $clock));

    expect($result)->toBe(0)
        ->and(array_keys($repository->records))->toBe([3])
        ->and($content)->toContain('Cleared 2 expired password reset token(s).');
});

it('reports when there is nothing to clear', function (): void {
    $clock = new FakeClock('2026-06-01 12:00:00');

    [$result, $content] = runClearResetTokens(
        clearResetTokensCommand(new InMemoryPasswordResetTokenRepository(), $clock),
    );

    expect($result)->toBe(0)
        ->and($content)->toContain('No expired password reset tokens to clear.');
});
