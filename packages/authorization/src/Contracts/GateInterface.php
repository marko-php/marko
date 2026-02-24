<?php

declare(strict_types=1);

namespace Marko\Authorization\Contracts;

use Marko\Authorization\AuthorizableInterface;
use Marko\Authorization\Exceptions\AuthorizationException;

interface GateInterface
{
    /**
     * Define an ability with a closure.
     *
     * @param callable(?AuthorizableInterface, mixed...): bool $callback
     */
    public function define(
        string $ability,
        callable $callback,
    ): void;

    /**
     * Check if the given ability is allowed.
     */
    public function allows(
        string $ability,
        mixed ...$arguments,
    ): bool;

    /**
     * Check if the given ability is denied.
     */
    public function denies(
        string $ability,
        mixed ...$arguments,
    ): bool;

    /**
     * Authorize the given ability. Throws on denial.
     *
     * @throws AuthorizationException
     */
    public function authorize(
        string $ability,
        mixed ...$arguments,
    ): bool;

    /**
     * Register a policy class for an entity class.
     *
     * @param class-string $entityClass
     * @param class-string $policyClass
     */
    public function policy(
        string $entityClass,
        string $policyClass,
    ): void;
}
