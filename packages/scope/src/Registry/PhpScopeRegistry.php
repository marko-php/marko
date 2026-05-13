<?php

declare(strict_types=1);

namespace Marko\Scope\Registry;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Scope\Axis\ScopeAxis;
use Marko\Scope\Exceptions\ScopeConfigurationException;
use Marko\Scope\Exceptions\UnknownAxisException;
use Marko\Scope\Hierarchy\ScopeHierarchy;

readonly class PhpScopeRegistry implements ScopeRegistryInterface
{
    /** @var array<string, ScopeAxis> */
    private array $axes;

    /**
     * @throws ConfigNotFoundException|ScopeConfigurationException
     */
    public function __construct(ConfigRepositoryInterface $config)
    {
        $rawAxes = $config->getArray('scope.axes');
        $this->axes = $this->buildAxes($rawAxes);
    }

    public function hasAxis(string $name): bool
    {
        return isset($this->axes[$name]);
    }

    /**
     * @throws UnknownAxisException
     */
    public function getAxis(string $name): ScopeAxis
    {
        if (!isset($this->axes[$name])) {
            throw UnknownAxisException::forAxis($name);
        }

        return $this->axes[$name];
    }

    /**
     * @return list<string>
     */
    public function listAxes(): array
    {
        return array_keys($this->axes);
    }

    /**
     * @throws UnknownAxisException
     */
    public function getHierarchy(string $axisName): ScopeHierarchy
    {
        return $this->getAxis($axisName)->hierarchy;
    }

    /**
     * @param array<mixed> $rawAxes
     * @return array<string, ScopeAxis>
     * @throws ScopeConfigurationException
     */
    private function buildAxes(array $rawAxes): array
    {
        $axes = [];

        foreach ($rawAxes as $name => $definition) {
            if (!is_array($definition)) {
                throw ScopeConfigurationException::malformedConfig(
                    (string) $name,
                    'axis definition must be an array',
                );
            }

            $paths = $definition['hierarchy'] ?? [];

            if (!is_array($paths)) {
                throw ScopeConfigurationException::malformedConfig(
                    (string) $name,
                    "'hierarchy' must be an array of paths",
                );
            }

            $hierarchy = new ScopeHierarchy($paths);
            $axes[(string) $name] = new ScopeAxis(name: (string) $name, hierarchy: $hierarchy);
        }

        return $axes;
    }
}
