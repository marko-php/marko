<?php

declare(strict_types=1);

namespace Marko\OAuth\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
readonly class RequiresScope
{
    /**
     * @var array<string>
     */
    public array $scopes;

    public function __construct(
        string ...$scopes,
    ) {
        $this->scopes = $scopes;
    }
}
