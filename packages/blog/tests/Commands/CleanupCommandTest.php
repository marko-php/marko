<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Commands;

use Marko\Blog\Commands\CleanupCommand;
use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Entity\VerificationToken;
use Marko\Blog\Services\TokenRepositoryInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use ReflectionClass;

/**
 * Helper to capture command output.
 *
 * @return array{stream: resource, output: Output}
 */
function createCleanupOutputStream(): array
{
    $stream = fopen('php://memory', 'r+');

    return [
        'stream' => $stream,
        'output' => new Output($stream),
    ];
}

/**
 * Helper to get output content from stream.
 *
 * @param resource $stream
 */
function getCleanupOutputContent(
    mixed $stream,
): string {
    rewind($stream);

    return stream_get_contents($stream);
}

/**
 * Helper to execute CleanupCommand.
 *
 * @param array<string> $args
 *
 * @return array{output: string, exitCode: int}
 */
function executeCleanupCommand(
    CleanupCommand $command,
    array $args = ['marko', 'blog:cleanup'],
): array {
    ['stream' => $stream, 'output' => $output] = createCleanupOutputStream();
    $input = new Input($args);

    $exitCode = $command->execute($input, $output);
    $result = getCleanupOutputContent($stream);

    return ['output' => $result, 'exitCode' => $exitCode];
}

it('is registered as blog:cleanup command', function (): void {
    $reflection = new ReflectionClass(CleanupCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1)
        ->and($attributes[0]->newInstance()->name)->toBe('blog:cleanup');
});

it('deletes email verification tokens older than configured expiry', function (): void {
    $capture = (object) ['deleteExpiredEmailTokensCalled' => false, 'expiryDaysUsed' => null];

    $tokenRepository = new StubTokenRepository($capture);
    $blogConfig = new StubBlogConfig(7, 365);
    $command = new CleanupCommand($tokenRepository, $blogConfig);

    executeCleanupCommand($command);

    expect($capture->deleteExpiredEmailTokensCalled)->toBeTrue()
        ->and($capture->expiryDaysUsed)->toBe(7);
});

it('deletes browser tokens older than configured cookie days', function (): void {
    $capture = (object) ['deleteExpiredBrowserTokensCalled' => false, 'cookieDaysUsed' => null];

    $tokenRepository = new StubTokenRepository($capture);
    $blogConfig = new StubBlogConfig(7, 365);
    $command = new CleanupCommand($tokenRepository, $blogConfig);

    executeCleanupCommand($command);

    expect($capture->deleteExpiredBrowserTokensCalled)->toBeTrue()
        ->and($capture->cookieDaysUsed)->toBe(365);
});

it('reports count of deleted email verification tokens', function (): void {
    $capture = (object) ['deletedEmailTokenCount' => 5];

    $tokenRepository = new StubTokenRepository($capture);
    $blogConfig = new StubBlogConfig(7, 365);
    $command = new CleanupCommand($tokenRepository, $blogConfig);

    ['output' => $output] = executeCleanupCommand($command);

    expect($output)->toContain('5')
        ->and($output)->toContain('email');
});

it('reports count of deleted browser tokens', function (): void {
    $capture = (object) ['deletedBrowserTokenCount' => 3];

    $tokenRepository = new StubTokenRepository($capture);
    $blogConfig = new StubBlogConfig(7, 365);
    $command = new CleanupCommand($tokenRepository, $blogConfig);

    ['output' => $output] = executeCleanupCommand($command);

    expect($output)->toContain('3')
        ->and($output)->toContain('browser');
});

it('handles case when nothing to clean up', function (): void {
    $capture = (object) [
        'deletedEmailTokenCount' => 0,
        'deletedBrowserTokenCount' => 0,
    ];

    $tokenRepository = new StubTokenRepository($capture);
    $blogConfig = new StubBlogConfig(7, 365);
    $command = new CleanupCommand($tokenRepository, $blogConfig);

    ['output' => $output, 'exitCode' => $exitCode] = executeCleanupCommand($command);

    expect($output)->toContain('0 email verification token(s) deleted')
        ->and($output)->toContain('0 browser token(s) deleted')
        ->and($exitCode)->toBe(0);
});

it('provides verbose output option', function (): void {
    $capture = (object) [
        'deletedEmailTokenCount' => 2,
        'deletedBrowserTokenCount' => 1,
    ];

    $tokenRepository = new StubTokenRepository($capture);
    $blogConfig = new StubBlogConfig(7, 365);
    $command = new CleanupCommand($tokenRepository, $blogConfig);

    // Without verbose - should not show configuration details
    ['output' => $normalOutput] = executeCleanupCommand($command);
    expect($normalOutput)->not->toContain('expiry')
        ->and($normalOutput)->not->toContain('7 days');

    // With verbose - should show configuration details
    ['output' => $verboseOutput] = executeCleanupCommand(
        $command,
        ['marko', 'blog:cleanup', '--verbose'],
    );
    expect($verboseOutput)->toContain('7')
        ->and($verboseOutput)->toContain('365');
});

it('returns success exit code on completion', function (): void {
    $capture = (object) [
        'deletedEmailTokenCount' => 5,
        'deletedBrowserTokenCount' => 3,
    ];

    $tokenRepository = new StubTokenRepository($capture);
    $blogConfig = new StubBlogConfig(7, 365);
    $command = new CleanupCommand($tokenRepository, $blogConfig);

    ['exitCode' => $exitCode] = executeCleanupCommand($command);

    expect($exitCode)->toBe(0);
});

/**
 * Stub blog config for testing.
 */
readonly class StubBlogConfig implements BlogConfigInterface
{
    public function __construct(
        private int $verificationTokenExpiryDays = 7,
        private int $verificationCookieDays = 365,
    ) {}

    public function getPostsPerPage(): int
    {
        return 10;
    }

    public function getCommentMaxDepth(): int
    {
        return 5;
    }

    public function getCommentRateLimitSeconds(): int
    {
        return 30;
    }

    public function getVerificationTokenExpiryDays(): int
    {
        return $this->verificationTokenExpiryDays;
    }

    public function getVerificationCookieDays(): int
    {
        return $this->verificationCookieDays;
    }

    public function getRoutePrefix(): string
    {
        return '/blog';
    }

    public function getVerificationCookieName(): string
    {
        return 'blog_verified';
    }

    public function getSiteName(): string
    {
        return 'Test Blog';
    }
}

/**
 * Stub token repository for testing.
 */
readonly class StubTokenRepository implements TokenRepositoryInterface
{
    public function __construct(
        private object $capture,
    ) {}

    public function save(
        VerificationToken $token,
    ): void {}

    public function findByToken(
        string $token,
    ): ?VerificationToken {
        return null;
    }

    public function findByCommentId(
        int $commentId,
    ): ?VerificationToken {
        return null;
    }

    public function findBrowserTokenForEmail(
        string $email,
    ): ?VerificationToken {
        return null;
    }

    public function delete(
        VerificationToken $token,
    ): void {}

    public function existsBy(
        array $criteria,
    ): bool {
        return false;
    }

    public function deleteExpiredEmailTokens(
        int $expiryDays,
    ): int {
        $this->capture->deleteExpiredEmailTokensCalled = true;
        $this->capture->expiryDaysUsed = $expiryDays;
        $this->capture->deletedEmailTokenCount ??= 0;

        return $this->capture->deletedEmailTokenCount;
    }

    public function deleteExpiredBrowserTokens(
        int $cookieDays,
    ): int {
        $this->capture->deleteExpiredBrowserTokensCalled = true;
        $this->capture->cookieDaysUsed = $cookieDays;
        $this->capture->deletedBrowserTokenCount ??= 0;

        return $this->capture->deletedBrowserTokenCount;
    }
}
