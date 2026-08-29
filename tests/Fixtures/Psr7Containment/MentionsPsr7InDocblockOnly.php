<?php

declare(strict_types=1);

namespace Marko\Tests\Fixtures\Psr7Containment;

/**
 * Not a real dependency — this class only talks about Psr\Http\Message\ResponseInterface
 * and Nyholm\Psr7\Response in its docblock, and returns their names as plain
 * strings. Neither should tokenize as a code-level symbol reference.
 */
readonly class MentionsPsr7InDocblockOnly
{
    public function describe(): string
    {
        return 'Psr\Http\Message\ResponseInterface and Nyholm\Psr7\Response are mentioned here only as text.';
    }
}
