<?php

declare(strict_types=1);

namespace Marko\Routing\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidRouteParameterException extends MarkoException
{
    public static function missingRequired(
        string $paramName,
        string $expectedType,
        string $controller,
        string $action,
    ): self {
        return new self(
            message: "Missing required parameter '$paramName' of type '$expectedType'",
            context: "While dispatching $controller::$action()",
            suggestion: "Provide a '$paramName' value in the route, POST body, or query string",
        );
    }
}
