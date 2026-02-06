<?php

declare(strict_types=1);

namespace Marko\Admin\Exceptions;

use Marko\Core\Exceptions\MarkoException;

class InvalidAdminConfigException extends MarkoException
{
    public static function routePrefixMustStartWithSlash(string $prefix): self
    {
        return new self(
            message: "Admin route prefix must start with '/', got: '$prefix'",
            context: 'While validating admin configuration',
            suggestion: "Update the 'admin.route_prefix' config to start with '/'",
        );
    }
}
