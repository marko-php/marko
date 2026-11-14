<?php

declare(strict_types=1);

namespace Marko\Routing;

use Marko\Routing\Exceptions\RouteException;

/**
 * One route. Every constructor argument is a plain string or list of strings,
 * so a definition can be rebuilt from cached data; everything else is derived
 * from the path.
 *
 * Path placeholders:
 *
 * - `{name}` matches one path segment.
 * - `{name:regex}` matches one value that satisfies the regex (non-capturing
 *   groups only), e.g. `{id:\d+}` or `{year:\d{4}}`.
 * - `{name*}` is a catch-all that matches the rest of the path, slashes
 *   included. It must be the whole final segment.
 *
 * Matched values are URL-decoded, and a decoded value that could walk a
 * filesystem path (see acceptsValue()) does not match.
 */
readonly class RouteDefinition
{
    private const string NAME_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /** @var array<int, string> Parameter names in path order */
    public array $parameters;

    /** @var array<string, string> Placeholder text as written in the path (e.g. '{id:\d+}'), keyed by parameter name */
    public array $placeholders;

    /** @var array<string, string> Regex constraints keyed by parameter name */
    public array $constraints;

    /** Name of the catch-all parameter, or null when the path has none. */
    public ?string $catchAll;

    public string $regex;

    /** True when the path has no parameters and can be matched by exact string lookup. */
    public bool $isStatic;

    /** Number of path segments that contain no parameter; higher sorts first among dynamic routes. */
    public int $staticSegmentCount;

    /** Length of the path before its first parameter; the tie-breaker after static segment count. */
    public int $staticPrefixLength;

    /**
     * @param array<int, string> $middleware
     * @param array<int, string> $withoutMiddleware Global or route middleware this route skips
     * @throws RouteException When the path has a malformed parameter, an invalid constraint or a misplaced catch-all
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $controller,
        public string $action,
        public array $middleware = [],
        public ?string $name = null,
        public array $withoutMiddleware = [],
    ) {
        $tokens = $this->tokenize($path);
        $parameters = [];
        $placeholders = [];
        $constraints = [];
        $catchAll = null;
        $pattern = '';
        $skeleton = '';

        foreach ($tokens as $index => $token) {
            if ($token['parameter'] === null) {
                $pattern .= preg_quote($token['text'], '#');
                $skeleton .= $token['text'];
                continue;
            }

            $name = $token['parameter'];
            $parameters[] = $name;
            $placeholders[$name] = $token['text'];
            $skeleton .= '{}';

            if ($token['catchAll']) {
                $this->assertCatchAllIsFinal($tokens, $index, $name);
                $catchAll = $name;
                $pattern .= '(?P<' . $name . '>.+)';
                continue;
            }

            if ($token['constraint'] !== null) {
                $constraints[$name] = $token['constraint'];
                $pattern .= '(?P<' . $name . '>' . $this->escapeDelimiter($token['constraint']) . ')';
                continue;
            }

            $pattern .= '(?P<' . $name . '>[^/]+)';
        }

        $this->parameters = $parameters;
        $this->placeholders = $placeholders;
        $this->constraints = $constraints;
        $this->catchAll = $catchAll;
        $this->regex = '#^' . $pattern . '$#';
        $this->isStatic = $parameters === [];
        $this->staticSegmentCount = count(array_filter(
            explode('/', $skeleton),
            fn (string $segment): bool => $segment !== ''
                && !str_contains($segment, '{'),
        ));
        $firstParameter = strpos($path, '{');
        $this->staticPrefixLength = $firstParameter === false ? strlen($path) : $firstParameter;
    }

    /**
     * A copy of this route dispatched to another controller, used when a
     * #[Preference] inherits a parent controller's route.
     */
    public function withController(
        string $controller,
    ): static {
        return clone($this, ['controller' => $controller]);
    }

    /**
     * Whether a value satisfies the parameter's constraint (always true for
     * unconstrained parameters).
     */
    public function satisfiesConstraint(
        string $parameter,
        string $value,
    ): bool {
        if (!isset($this->constraints[$parameter])) {
            return true;
        }

        return preg_match('#^(?:' . $this->escapeDelimiter($this->constraints[$parameter]) . ')$#', $value) === 1;
    }

    /**
     * Whether a decoded value is safe to hand to the controller.
     *
     * Matching runs on the raw, still-encoded path, so `%2F` and `%2E%2E`
     * only become `/` and `..` after decoding. No value may contain a NUL
     * byte or a `.`/`..` path segment. A catch-all may contain `/` (that is
     * its purpose); every other parameter, constrained or not, may not.
     */
    public function acceptsValue(
        string $parameter,
        string $value,
    ): bool {
        if (str_contains($value, "\0")) {
            return false;
        }

        if ($parameter !== $this->catchAll) {
            return !str_contains($value, '/')
                && $value !== '.'
                && $value !== '..';
        }

        foreach (explode('/', $value) as $segment) {
            if (
                $segment === '.'
                || $segment === '..'
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Split the path into static text and parameter placeholders, tracking
     * brace depth so a constraint may itself contain braces.
     *
     * @return array<int, array{text: string, parameter: ?string, constraint: ?string, catchAll: bool}>
     * @throws RouteException
     */
    private function tokenize(
        string $path,
    ): array {
        $tokens = [];
        $seen = [];
        $length = strlen($path);
        $static = '';
        $offset = 0;

        while ($offset < $length) {
            $char = $path[$offset];

            if ($char === '}') {
                throw RouteException::invalidParameter($path, '}', 'Unmatched closing brace');
            }

            if ($char !== '{') {
                $static .= $char;
                $offset++;
                continue;
            }

            $close = $this->findClosingBrace($path, $offset);
            $placeholder = substr($path, $offset + 1, $close - $offset - 1);

            if ($static !== '') {
                $tokens[] = ['text' => $static, 'parameter' => null, 'constraint' => null, 'catchAll' => false];
                $static = '';
            }

            $token = $this->parsePlaceholder($path, $placeholder);

            if (isset($seen[$token['parameter']])) {
                throw RouteException::invalidParameter(
                    $path,
                    (string) $token['parameter'],
                    'The parameter name is used more than once',
                );
            }

            $seen[$token['parameter']] = true;
            $tokens[] = $token;
            $offset = $close + 1;
        }

        if ($static !== '') {
            $tokens[] = ['text' => $static, 'parameter' => null, 'constraint' => null, 'catchAll' => false];
        }

        return $tokens;
    }

    /**
     * @throws RouteException
     */
    private function findClosingBrace(
        string $path,
        int $open,
    ): int {
        $depth = 0;
        $length = strlen($path);

        for ($offset = $open; $offset < $length; $offset++) {
            $char = $path[$offset];

            if ($char === '\\') {
                $offset++;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;

                if ($depth === 0) {
                    return $offset;
                }
            }
        }

        throw RouteException::invalidParameter($path, substr($path, $open), 'Missing closing brace');
    }

    /**
     * @return array{text: string, parameter: string, constraint: ?string, catchAll: bool}
     * @throws RouteException
     */
    private function parsePlaceholder(
        string $path,
        string $placeholder,
    ): array {
        $catchAll = false;
        $constraint = null;
        $name = $placeholder;
        $colon = strpos($placeholder, ':');

        if ($colon !== false) {
            $name = substr($placeholder, 0, $colon);
            $constraint = substr($placeholder, $colon + 1);
        } elseif (str_ends_with($placeholder, '*')) {
            $name = substr($placeholder, 0, -1);
            $catchAll = true;
        }

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw RouteException::invalidParameter(
                $path,
                $name,
                'Parameter names must start with a letter or underscore and contain only letters, digits and underscores',
            );
        }

        if ($constraint !== null) {
            $this->assertValidConstraint($path, $name, $constraint);
        }

        return ['text' => '{' . $placeholder . '}', 'parameter' => $name, 'constraint' => $constraint, 'catchAll' => $catchAll];
    }

    /**
     * @throws RouteException
     */
    private function assertValidConstraint(
        string $path,
        string $name,
        string $constraint,
    ): void {
        if ($constraint === '') {
            throw RouteException::invalidConstraint($path, $name, $constraint, 'The constraint is empty');
        }

        $escaped = $this->escapeDelimiter($constraint);

        $compileError = null;
        set_error_handler(function (int $severity, string $message) use (&$compileError): bool {
            $compileError = $message;

            return true;
        });

        try {
            $valid = preg_match('#^(?:' . $escaped . ')$#', '') !== false;
        } finally {
            restore_error_handler();
        }

        if (!$valid) {
            throw RouteException::invalidConstraint($path, $name, $constraint, $compileError ?? preg_last_error_msg());
        }

        // An empty alternative always matches, and PREG_UNMATCHED_AS_NULL reports
        // every group, so any entry past the full match is a capturing group.
        preg_match('#(?:' . $escaped . ')|#', '', $matches, PREG_UNMATCHED_AS_NULL);

        if (count($matches) > 1) {
            throw RouteException::capturingGroupInConstraint($path, $name, $constraint);
        }
    }

    /**
     * @param array<int, array{text: string, parameter: ?string, constraint: ?string, catchAll: bool}> $tokens
     * @throws RouteException
     */
    private function assertCatchAllIsFinal(
        array $tokens,
        int $index,
        string $name,
    ): void {
        $isLast = $index === array_key_last($tokens);
        $previous = $tokens[$index - 1] ?? null;
        $startsSegment = $previous !== null
            && $previous['parameter'] === null
            && str_ends_with(
                $previous['text'],
                '/',
            );

        if (
            !$isLast
            || !$startsSegment
        ) {
            throw RouteException::catchAllNotFinal($this->path, $name);
        }
    }

    private function escapeDelimiter(
        string $regex,
    ): string {
        return (string) preg_replace('/(?<!\\\\)#/', '\\#', $regex);
    }
}
