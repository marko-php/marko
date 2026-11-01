<?php

declare(strict_types=1);

use Marko\Database\Connection\ConnectionInterface;
use Marko\Database\Connection\TransactionInterface;
use Marko\Database\Exceptions\LockException;
use Marko\Database\Repository\Repository;
use Marko\Integration\Fixture\Entity\Author;
use Marko\Integration\Fixture\Entity\Book;
use Marko\Integration\Fixture\Repository\AuthorRepository;
use Marko\Integration\Fixture\Repository\BookRepository;

/*
 * Transactions through the real module wiring against real Postgres:
 * TransactionInterface, the repositories and their query builders all share
 * one connection (#159), so nested transactions, after-commit callbacks and
 * row locks (#176) cover every write made through them.
 */

pest()->group('integration-services');

beforeEach(fn () => setUpIntegrationTest($this));

afterEach(fn () => tearDownIntegrationTest($this));

function saveIntegrationAuthor(AuthorRepository $repository, string $name): Author
{
    $author = new Author();
    $author->name = $name;
    $repository->save($author);

    return $author;
}

/**
 * @return list<string>
 */
function integrationAuthorNames(ConnectionInterface $connection): array
{
    return array_column($connection->query('SELECT name FROM authors ORDER BY id'), 'name');
}

it('rolls back a savepoint without rolling back the outer transaction', function (): void {
    $container = $this->app->container;
    $transaction = $container->get(TransactionInterface::class);
    $authors = $container->get(AuthorRepository::class);

    $transaction->transaction(function () use ($transaction, $authors): void {
        saveIntegrationAuthor($authors, 'Outer');

        try {
            $transaction->transaction(function () use ($authors): void {
                saveIntegrationAuthor($authors, 'Inner');

                throw new RuntimeException('Inner failure');
            });
        } catch (RuntimeException) {
            // Only the savepoint rolls back.
        }
    });

    expect(integrationAuthorNames($container->get(ConnectionInterface::class)))->toBe(['Outer'])
        ->and($transaction->transactionLevel())->toBe(0);
})->issue(176);

it('runs an after-commit callback only when the transaction commits', function (): void {
    $container = $this->app->container;
    $transaction = $container->get(TransactionInterface::class);
    $authors = $container->get(AuthorRepository::class);
    $log = new ArrayObject();

    try {
        $transaction->transaction(function () use ($transaction, $authors, $log): void {
            saveIntegrationAuthor($authors, 'Rolled back');
            $transaction->afterCommit(fn () => $log->append('rolled-back transaction'));

            throw new RuntimeException('Rollback');
        });
    } catch (RuntimeException) {
        // Expected.
    }

    $transaction->transaction(function () use ($transaction, $authors, $log): void {
        saveIntegrationAuthor($authors, 'Committed');
        $transaction->transaction(
            fn () => $transaction->afterCommit(
                fn () => $log->append('committed at level ' . $transaction->transactionLevel()),
            ),
        );
        $log->append('callback deferred');
    });

    expect($log->getArrayCopy())->toBe(['callback deferred', 'committed at level 0'])
        ->and(integrationAuthorNames($container->get(ConnectionInterface::class)))->toBe(['Committed']);
})->issue(176);

it('locks rows through a repository query inside a container-resolved transaction', function (): void {
    $container = $this->app->container;
    $transaction = $container->get(TransactionInterface::class);
    $authors = $container->get(AuthorRepository::class);
    $author = saveIntegrationAuthor($authors, 'Locked');

    $locked = $transaction->transaction(
        fn () => $authors->query()->where('id', '=', $author->id)->lockForUpdate()->firstEntity(),
    );
    $outside = fn () => $authors->query()->where('id', '=', $author->id)->lockForUpdate()->firstEntity();

    expect($locked)->toBeInstanceOf(Author::class)
        ->and($locked?->name)->toBe('Locked')
        ->and($outside)->toThrow(LockException::class, "Cannot lock rows of 'authors' outside a transaction");
})->issue(176);

it('rolls back writes made through two repositories when a transaction spanning both fails', function (): void {
    $container = $this->app->container;
    $transaction = $container->get(TransactionInterface::class);
    $authors = $container->get(AuthorRepository::class);
    $books = $container->get(BookRepository::class);

    $attempt = fn () => $transaction->transaction(function () use ($authors, $books): void {
        $author = saveIntegrationAuthor($authors, 'Octavia E. Butler');

        $book = new Book();
        $book->authorId = (int) $author->id;
        $book->title = 'Kindred';
        $books->save($book);

        throw new RuntimeException('Fail after both writes');
    });

    $connection = $container->get(ConnectionInterface::class);

    expect($attempt)->toThrow(RuntimeException::class, 'Fail after both writes')
        ->and($connection->query('SELECT COUNT(*) AS total FROM authors')[0]['total'])->toBe(0)
        ->and($connection->query('SELECT COUNT(*) AS total FROM books')[0]['total'])->toBe(0)
        ->and($transaction->transactionLevel())->toBe(0);
})->issue(159);

it('resolves TransactionInterface to the same connection the repositories use', function (): void {
    $container = $this->app->container;
    $repositoryConnection = new ReflectionProperty(Repository::class, 'connection');
    $connection = $container->get(ConnectionInterface::class);

    expect($container->get(TransactionInterface::class))->toBe($connection)
        ->and($repositoryConnection->getValue($container->get(AuthorRepository::class)))->toBe($connection)
        ->and($repositoryConnection->getValue($container->get(BookRepository::class)))->toBe($connection);
})->issue(159);
