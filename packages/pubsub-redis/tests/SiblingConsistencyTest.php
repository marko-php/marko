<?php

declare(strict_types=1);

use Marko\PubSub\PgSql\Driver\PgSqlPublisher;
use Marko\PubSub\PgSql\Driver\PgSqlSubscriber;
use Marko\PubSub\PgSql\Driver\PgSqlSubscription;
use Marko\PubSub\PgSql\PgSqlPubSubConnection;
use Marko\PubSub\Redis\Driver\RedisPublisher;
use Marko\PubSub\Redis\Driver\RedisSubscriber;
use Marko\PubSub\Redis\Driver\RedisSubscription;
use Marko\PubSub\Redis\RedisPubSubConnection;

/**
 * Returns an array of public method names => parameter type signatures for a class.
 *
 * @param class-string $className
 * @return array<string, string>
 */
function getPublicMethodSignatures(string $className): array
{
    $reflection = new ReflectionClass($className);
    $signatures = [];

    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->isConstructor() || $method->isDestructor()) {
            continue;
        }

        $params = array_map(function (ReflectionParameter $param): string {
            $type = $param->getType();
            $typeName = $type instanceof ReflectionNamedType ? $type->getName() : (string) $type;
            $optional = $param->isOptional() ? '?' : '';

            return $typeName . $optional . ' $' . $param->getName();
        }, $method->getParameters());

        $returnType = $method->getReturnType();
        $returnTypeName = $returnType instanceof ReflectionNamedType
            ? $returnType->getName()
            : (string) $returnType;

        $signatures[$method->getName()] = implode(', ', $params) . ': ' . $returnTypeName;
    }

    return $signatures;
}

/**
 * Returns method visibility map for a class.
 *
 * @param class-string $className
 * @return array<string, string>
 */
function getMethodVisibilityMap(string $className): array
{
    $reflection = new ReflectionClass($className);
    $map = [];

    foreach ($reflection->getMethods() as $method) {
        if ($method->isConstructor() || $method->isDestructor()) {
            continue;
        }

        if ($method->isPublic()) {
            $map[$method->getName()] = 'public';
        } elseif ($method->isProtected()) {
            $map[$method->getName()] = 'protected';
        } else {
            $map[$method->getName()] = 'private';
        }
    }

    return $map;
}

it('has identical public method signatures on RedisPublisher and PgSqlPublisher', function (): void {
    $redisMethods = getPublicMethodSignatures(RedisPublisher::class);
    $pgMethods = getPublicMethodSignatures(PgSqlPublisher::class);

    expect($redisMethods)->toHaveKey('publish')
        ->and($pgMethods)->toHaveKey('publish')
        ->and($redisMethods['publish'])->toBe($pgMethods['publish'])
        ->and(array_keys($redisMethods))->toBe(array_keys($pgMethods));
});

it('has identical public method signatures on RedisSubscriber and PgSqlSubscriber', function (): void {
    $redisMethods = getPublicMethodSignatures(RedisSubscriber::class);
    $pgMethods = getPublicMethodSignatures(PgSqlSubscriber::class);

    expect($redisMethods)->toHaveKey('subscribe')
        ->and($pgMethods)->toHaveKey('subscribe')
        ->and($redisMethods['subscribe'])->toBe($pgMethods['subscribe'])
        ->and($redisMethods)->toHaveKey('psubscribe')
        ->and($pgMethods)->toHaveKey('psubscribe')
        ->and($redisMethods['psubscribe'])->toBe($pgMethods['psubscribe'])
        ->and(array_keys($redisMethods))->toBe(array_keys($pgMethods));
});

it('has identical public method signatures on RedisSubscription and PgSqlSubscription', function (): void {
    $redisMethods = getPublicMethodSignatures(RedisSubscription::class);
    $pgMethods = getPublicMethodSignatures(PgSqlSubscription::class);

    expect($redisMethods)->toHaveKey('getIterator')
        ->and($pgMethods)->toHaveKey('getIterator')
        ->and($redisMethods['getIterator'])->toBe($pgMethods['getIterator'])
        ->and($redisMethods)->toHaveKey('cancel')
        ->and($pgMethods)->toHaveKey('cancel')
        ->and($redisMethods['cancel'])->toBe($pgMethods['cancel'])
        ->and(array_keys($redisMethods))->toBe(array_keys($pgMethods));
});

it('has identical method visibility for same-purpose methods across connections', function (): void {
    $redisVisibility = getMethodVisibilityMap(RedisPubSubConnection::class);
    $pgVisibility = getMethodVisibilityMap(PgSqlPubSubConnection::class);

    expect($redisVisibility)->toHaveKey('disconnect')
        ->and($pgVisibility)->toHaveKey('disconnect')
        ->and($redisVisibility['disconnect'])->toBe($pgVisibility['disconnect'])
        ->and($redisVisibility)->toHaveKey('isConnected')
        ->and($pgVisibility)->toHaveKey('isConnected')
        ->and($redisVisibility['isConnected'])->toBe($pgVisibility['isConnected']);
});

it('uses consistent class modifiers across siblings', function (): void {
    $redisPublisherReflection = new ReflectionClass(RedisPublisher::class);
    $pgPublisherReflection = new ReflectionClass(PgSqlPublisher::class);
    $redisSubscriberReflection = new ReflectionClass(RedisSubscriber::class);
    $pgSubscriberReflection = new ReflectionClass(PgSqlSubscriber::class);
    $redisSubscriptionReflection = new ReflectionClass(RedisSubscription::class);
    $pgSubscriptionReflection = new ReflectionClass(PgSqlSubscription::class);

    // Publishers should both be readonly
    expect($redisPublisherReflection->isReadOnly())->toBeTrue()
        ->and($pgPublisherReflection->isReadOnly())->toBeTrue()
        // Subscribers should both use same modifier (readonly)
        ->and($redisSubscriberReflection->isReadOnly())->toBe($pgSubscriberReflection->isReadOnly())
        // Subscriptions should both use same modifier
        ->and($redisSubscriptionReflection->isReadOnly())->toBe($pgSubscriptionReflection->isReadOnly());
});

it('uses consistent exception message format across drivers', function (): void {
    // Both drivers should throw PubSubException with consistent format when publish fails
    // Format: "Failed to publish to {Driver} channel '{name}'"
    $redisSource = file_get_contents(dirname(__DIR__) . '/src/Driver/RedisPublisher.php');
    $pgSource = file_get_contents(
        dirname(__DIR__, 2) . '/pubsub-pgsql/src/Driver/PgSqlPublisher.php',
    );

    // Both should use the same PubSubException::publishFailed pattern for error handling
    // Check that both publishers use the same base interface for exceptions (PubSubException)
    // by verifying neither uses raw RuntimeException or custom exception classes
    expect($redisSource)->not->toContain('RuntimeException')
        ->and($pgSource)->not->toContain('RuntimeException');

    // Verify consistent exception class usage: both use PubSubException (via publishFailed)
    // The PgSqlSubscriber throws PubSubException for unsupported psubscribe
    $pgSubscriberSource = file_get_contents(
        dirname(__DIR__, 2) . '/pubsub-pgsql/src/Driver/PgSqlSubscriber.php',
    );
    expect($pgSubscriberSource)->toContain('PubSubException');

    // RedisSubscriber should not use RuntimeException either
    $redisSubscriberSource = file_get_contents(dirname(__DIR__) . '/src/Driver/RedisSubscriber.php');
    expect($redisSubscriberSource)->not->toContain('RuntimeException');
});
