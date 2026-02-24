<?php

declare(strict_types=1);

namespace Marko\Authorization\Exceptions;

use Exception;
use Throwable;

class AuthorizationException extends Exception
{
    public function __construct(
        string $message,
        private readonly string $ability = '',
        private readonly string $resource = '',
        private readonly string $context = '',
        private readonly string $suggestion = '',
        int $code = 0,
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            $message,
            $code,
            $previous,
        );
    }

    public function getAbility(): string
    {
        return $this->ability;
    }

    public function getResource(): string
    {
        return $this->resource;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getSuggestion(): string
    {
        return $this->suggestion;
    }

    public static function forbidden(
        string $ability,
        string $resource,
    ): self {
        return new self(
            message: 'Forbidden',
            ability: $ability,
            resource: $resource,
            context: "Unable to perform '$ability' on '$resource'",
            suggestion: 'You do not have permission to perform this action',
        );
    }

    public static function missingPolicy(
        string $entityClass,
        string $ability,
    ): self {
        return new self(
            message: "No policy registered for '$entityClass'",
            ability: $ability,
            resource: $entityClass,
            context: "Attempted to check ability '$ability' on entity '$entityClass' but no policy is registered",
            suggestion: "Register a policy for '$entityClass' using Gate::policy($entityClass, YourPolicyClass::class)",
        );
    }
}
