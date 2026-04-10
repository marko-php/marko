<?php

declare(strict_types=1);

namespace Marko\Layout;

use Marko\Routing\Http\Request;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;

readonly class ComponentDataResolver
{
    /**
     * @param array<string, mixed> $routeParams
     * @return array<string, mixed>
     * @throws ReflectionException
     */
    public function resolve(object $component, array $routeParams, Request $request): array
    {
        if (!method_exists($component, 'data')) {
            return [];
        }

        $reflection = new ReflectionMethod($component, 'data');
        $parameters = [];

        foreach ($reflection->getParameters() as $param) {
            $name = $param->getName();
            $type = $param->getType();

            if ($type instanceof ReflectionNamedType && $type->getName() === Request::class) {
                $parameters[] = $request;
                continue;
            }

            if (array_key_exists($name, $routeParams)) {
                $parameters[] = $this->castToType($routeParams[$name], $type);
            } elseif ($param->isDefaultValueAvailable()) {
                $parameters[] = $param->getDefaultValue();
            } else {
                $parameters[] = null;
            }
        }

        return $component->data(...$parameters);
    }

    private function castToType(mixed $value, ?ReflectionType $type): mixed
    {
        if (!$type instanceof ReflectionNamedType) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            default => $value,
        };
    }
}
