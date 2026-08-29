<?php

declare(strict_types=1);

namespace Marko\Tests\Support\MiddlewareDecoration;

use PhpToken;

/**
 * Detects the "rebuild" anti-pattern in HTTP middleware: constructing a
 * fresh `Response` after already calling `$next()`, instead of decorating
 * the response `$next()` returned.
 *
 * Rule: within a single method (or closure) body, no `new *Response(` may
 * appear after the first `$next(` call. Constructing a genuinely fresh
 * response before calling `$next()`, or inside a separate helper method,
 * is allowed and not flagged.
 *
 * Uses `PhpToken::tokenize()` (built on `token_get_all()`) rather than
 * regex, since regex cannot reliably track method boundaries or distinguish
 * a real `new Response(` from one mentioned in a comment or string.
 */
readonly class MiddlewareDecorationDetector
{
    private const string NEXT_VARIABLE = '$next';

    /**
     * @var list<string>
     */
    private const array RESPONSE_ACCESSORS = ['body', 'statusCode', 'headers'];

    /**
     * @param list<string> $files
     * @return list<array{file: string, line: int, message: string}>
     */
    public function scan(array $files): array
    {
        $violations = [];

        foreach ($files as $file) {
            $violations = [...$violations, ...$this->scanFile($file)];
        }

        return $violations;
    }

    /**
     * @return list<array{file: string, line: int, message: string}>
     */
    private function scanFile(string $file): array
    {
        $code = file_get_contents($file);

        if ($code === false) {
            return [];
        }

        $tokens = PhpToken::tokenize($code);
        $violations = [];

        $depth = 0;
        /** @var list<array{depth: int, startIndex: int, sawNext: bool}> $contextStack */
        $contextStack = [];

        $awaitingFunctionBody = false;
        $functionParenDepth = 0;
        $seenParenForFunction = false;

        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = $token->text;

            if ($token->id === T_FUNCTION) {
                $awaitingFunctionBody = true;
                $functionParenDepth = 0;
                $seenParenForFunction = false;
                continue;
            }

            if ($awaitingFunctionBody) {
                if ($text === '(') {
                    $functionParenDepth++;
                    $seenParenForFunction = true;
                } elseif ($text === ')') {
                    $functionParenDepth--;
                } elseif ($seenParenForFunction && $functionParenDepth === 0 && $text === '{') {
                    $depth++;
                    $contextStack[] = ['depth' => $depth, 'startIndex' => $i, 'sawNext' => false];
                    $awaitingFunctionBody = false;
                } elseif ($seenParenForFunction && $functionParenDepth === 0 && $text === ';') {
                    $awaitingFunctionBody = false;
                }

                continue;
            }

            if ($text === '{') {
                $depth++;
                continue;
            }

            if ($text === '}') {
                $topIndex = array_key_last($contextStack);

                if ($topIndex !== null && $contextStack[$topIndex]['depth'] === $depth) {
                    array_pop($contextStack);
                }

                $depth--;
                continue;
            }

            $contextIndex = array_key_last($contextStack);

            if ($contextIndex === null) {
                continue;
            }

            if ($token->id === T_VARIABLE && $text === self::NEXT_VARIABLE) {
                $lookahead = $this->nextSignificantToken($tokens, $i);

                if ($lookahead !== null && $lookahead->text === '(' && !$this->isBareReturnOfNextCall($tokens, $i)) {
                    $contextStack[$contextIndex]['sawNext'] = true;
                }

                continue;
            }

            if ($token->id === T_NEW && $contextStack[$contextIndex]['sawNext']) {
                $className = $this->readClassName($tokens, $i);

                if ($className !== null && str_ends_with($className, 'Response')) {
                    $violations[] = [
                        'file' => $file,
                        'line' => $token->line,
                        'message' => $this->violationMessage(
                            file: $file,
                            line: $token->line,
                            tokens: $tokens,
                            startIndex: $contextStack[$contextIndex]['startIndex'],
                            newIndex: $i,
                        ),
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function nextSignificantToken(
        array $tokens,
        int $index,
    ): ?PhpToken {
        $nextIndex = $this->nextSignificantIndex($tokens, $index);

        return $nextIndex === null ? null : $tokens[$nextIndex];
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function nextSignificantIndex(
        array $tokens,
        int $index,
    ): ?int {
        $count = count($tokens);

        for ($j = $index + 1; $j < $count; $j++) {
            if (!$tokens[$j]->isIgnorable()) {
                return $j;
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function previousSignificantToken(
        array $tokens,
        int $index,
    ): ?PhpToken {
        for ($j = $index - 1; $j >= 0; $j--) {
            if (!$tokens[$j]->isIgnorable()) {
                return $tokens[$j];
            }
        }

        return null;
    }

    /**
     * A bare `return $next($request);` statement terminates its branch
     * immediately and has no bearing on code reached only via a sibling
     * branch that never called $next() — for example the common
     * short-circuit `if (...) { return $next($request); }` guard clause
     * that precedes an unrelated, genuinely fresh response later in the
     * same method. Only count $next() as "consumed" for the rest of the
     * method when its result is kept (assigned, chained, or otherwise
     * used) rather than returned bare.
     *
     * @param list<PhpToken> $tokens
     */
    private function isBareReturnOfNextCall(
        array $tokens,
        int $variableIndex,
    ): bool {
        $previous = $this->previousSignificantToken($tokens, $variableIndex);

        if ($previous === null || $previous->id !== T_RETURN) {
            return false;
        }

        $openParenIndex = $this->nextSignificantIndex($tokens, $variableIndex);

        if ($openParenIndex === null) {
            return false;
        }

        $closingParenIndex = $this->matchingCloseParenIndex($tokens, $openParenIndex);

        if ($closingParenIndex === null) {
            return false;
        }

        $afterCall = $this->nextSignificantToken($tokens, $closingParenIndex);

        return $afterCall !== null && $afterCall->text === ';';
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function matchingCloseParenIndex(
        array $tokens,
        int $openParenIndex,
    ): ?int {
        $count = count($tokens);
        $depth = 0;

        for ($j = $openParenIndex; $j < $count; $j++) {
            if ($tokens[$j]->text === '(') {
                $depth++;
            } elseif ($tokens[$j]->text === ')') {
                $depth--;

                if ($depth === 0) {
                    return $j;
                }
            }
        }

        return null;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function readClassName(
        array $tokens,
        int $newIndex,
    ): ?string {
        $nameTokenIds = [T_STRING, T_NS_SEPARATOR, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE];
        $count = count($tokens);
        $name = '';

        for ($j = $newIndex + 1; $j < $count; $j++) {
            $token = $tokens[$j];

            if ($token->isIgnorable()) {
                continue;
            }

            if (!in_array($token->id, $nameTokenIds, true)) {
                break;
            }

            $name .= $token->text;
        }

        return $name === '' ? null : $name;
    }

    /**
     * @param list<PhpToken> $tokens
     */
    private function violationMessage(
        string $file,
        int $line,
        array $tokens,
        int $startIndex,
        int $newIndex,
    ): string {
        $suffix = $this->referencesResponseAccessors($tokens, $startIndex, $newIndex)
            ? ' It even copies fields off the response $next() returned before rebuilding'
                . ' — decorate that response directly instead.'
            : '';

        return sprintf(
            '%s:%d constructs a new Response after calling $next(). This is the rebuild anti-pattern.%s '
            . 'Decorate the response $next() returned with Response::withHeader(), withHeaders(), '
            . 'withStatus(), or withCookie() instead of rebuilding it from scratch.',
            $file,
            $line,
            $suffix,
        );
    }

    /**
     * Secondary signal only, used to strengthen the failure message: does
     * the enclosing method body call ->body(), ->statusCode(), or
     * ->headers() anywhere before the offending `new Response(`? This
     * catches the case where fields are copied into a local variable first
     * (as the original InertiaMiddleware bug did) as well as direct
     * argument provenance.
     *
     * @param list<PhpToken> $tokens
     */
    private function referencesResponseAccessors(
        array $tokens,
        int $startIndex,
        int $newIndex,
    ): bool {
        for ($j = $startIndex; $j < $newIndex; $j++) {
            if ($tokens[$j]->id !== T_OBJECT_OPERATOR) {
                continue;
            }

            $accessor = $this->nextSignificantToken($tokens, $j);

            if ($accessor !== null && in_array($accessor->text, self::RESPONSE_ACCESSORS, true)) {
                return true;
            }
        }

        return false;
    }
}
