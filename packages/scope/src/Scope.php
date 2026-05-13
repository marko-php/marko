<?php

declare(strict_types=1);

namespace Marko\Scope;

use Marko\Scope\Exceptions\ScopeConfigurationException;

readonly class Scope
{
    public function __construct(
        public string $axisName,
        public string $path,
    ) {}

    public function toString(): string
    {
        return $this->axisName . ':' . $this->path;
    }

    public function equals(self $other): bool
    {
        return $this->axisName === $other->axisName
            && $this->path === $other->path;
    }

    /**
     * @throws ScopeConfigurationException
     */
    public static function fromString(string $scope): self
    {
        $parts = explode(':', $scope, 2);

        if (count($parts) !== 2 || $parts[0] === '' || $parts[1] === '') {
            throw ScopeConfigurationException::malformedString($scope);
        }

        return new self(axisName: $parts[0], path: $parts[1]);
    }
}
