<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Repositories;

use Marko\Blog\Entity\VerificationToken;
use Marko\Blog\Repositories\TokenRepository;
use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\StatementInterface;
use Marko\Database\Entity\EntityHydrator;
use Marko\Database\Entity\EntityMetadataFactory;
use RuntimeException;

function createTokenMockConnection(
    array $queryResult = [],
    ?array &$executeHistory = null,
): ConnectionInterface {
    $executeHistory ??= [];

    return new class ($queryResult, $executeHistory) implements ConnectionInterface
    {
        public function __construct(
            private array $queryResult,
            private array &$executeHistory,
        ) {}

        public function connect(): void {}

        public function disconnect(): void {}

        public function isConnected(): bool
        {
            return true;
        }

        /**
         * @param array<mixed> $bindings
         * @return array<array<string, mixed>>
         */
        public function query(
            string $sql,
            array $bindings = [],
        ): array {
            $this->executeHistory[] = ['sql' => $sql, 'bindings' => $bindings];

            return $this->queryResult;
        }

        /**
         * @param array<mixed> $bindings
         */
        public function execute(
            string $sql,
            array $bindings = [],
        ): int {
            $this->executeHistory[] = ['sql' => $sql, 'bindings' => $bindings];

            return 1;
        }

        public function prepare(
            string $sql,
        ): StatementInterface {
            throw new RuntimeException('Not implemented');
        }

        public function lastInsertId(): int
        {
            return 42;
        }
    };
}

function makeTokenRepository(
    array $queryResult = [],
    ?array &$history = null,
): TokenRepository {
    $connection = createTokenMockConnection($queryResult, $history);
    $metadataFactory = new EntityMetadataFactory();
    $hydrator = new EntityHydrator();

    return new TokenRepository($connection, $metadataFactory, $hydrator);
}

it('saves a verification token without constructor override', function (): void {
    $history = [];
    $repo = makeTokenRepository(history: $history);

    $token = new VerificationToken();
    $token->token = 'abc123';
    $token->email = 'user@example.com';
    $token->type = 'email';

    $repo->save($token);

    expect($history)->not->toBeEmpty()
        ->and($history[0]['sql'])->toContain('INSERT INTO verification_tokens');
});

it('deletes a verification token without method override', function (): void {
    $history = [];
    $repo = makeTokenRepository(history: $history);

    $token = new VerificationToken();
    $token->id = 5;
    $token->token = 'abc123';
    $token->email = 'user@example.com';
    $token->type = 'email';

    $repo->delete($token);

    expect($history)->not->toBeEmpty()
        ->and($history[0]['sql'])->toContain('DELETE FROM verification_tokens');
});

it('finds a token by token string', function (): void {
    $repo = makeTokenRepository([
        [
            'id' => 1,
            'token' => 'mytoken',
            'email' => 'user@example.com',
            'type' => 'email',
            'comment_id' => null,
            'created_at' => null,
            'expires_at' => null,
        ],
    ]);

    $result = $repo->findByToken('mytoken');

    expect($result)->toBeInstanceOf(VerificationToken::class)
        ->and($result->token)->toBe('mytoken')
        ->and($result->email)->toBe('user@example.com');
});

it('finds a token by comment id', function (): void {
    $repo = makeTokenRepository([
        [
            'id' => 2,
            'token' => 'commenttoken',
            'email' => 'user@example.com',
            'type' => 'email',
            'comment_id' => 10,
            'created_at' => null,
            'expires_at' => null,
        ],
    ]);

    $result = $repo->findByCommentId(10);

    expect($result)->toBeInstanceOf(VerificationToken::class)
        ->and($result->commentId)->toBe(10);
});

it('finds a browser token by email', function (): void {
    $repo = makeTokenRepository([
        [
            'id' => 3,
            'token' => 'browsertoken',
            'email' => 'user@example.com',
            'type' => 'browser',
            'comment_id' => null,
            'created_at' => null,
            'expires_at' => null,
        ],
    ]);

    $result = $repo->findBrowserTokenForEmail('user@example.com');

    expect($result)->toBeInstanceOf(VerificationToken::class)
        ->and($result->type)->toBe('browser')
        ->and($result->email)->toBe('user@example.com');
});
