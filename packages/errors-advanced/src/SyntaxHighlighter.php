<?php

declare(strict_types=1);

namespace Marko\ErrorsAdvanced;

class SyntaxHighlighter
{
    public function highlight(
        string $code,
    ): string {
        $tokens = token_get_all($code);
        $output = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $tokenType = $token[0];
                $tokenValue = htmlspecialchars($token[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $class = $this->getTokenClass($tokenType);
                $output .= "<span class=\"$class\">$tokenValue</span>";
            } else {
                $output .= htmlspecialchars($token, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $output;
    }

    public function highlightWithContext(
        string $code,
        int $errorLine,
        int $contextLines = 3,
    ): string {
        $lines = explode("\n", $code);
        $startLine = max(1, $errorLine - $contextLines);
        $endLine = min(count($lines), $errorLine + $contextLines);

        $contextCode = array_slice($lines, $startLine - 1, $endLine - $startLine + 1);
        $contextCodeWithTag = "<?php\n" . implode("\n", $contextCode);

        if (str_starts_with(trim($lines[$startLine - 1] ?? ''), '<?php')) {
            $contextCodeWithTag = implode("\n", $contextCode);
        }

        return $this->highlight($contextCodeWithTag);
    }

    private function getTokenClass(
        int $tokenType,
    ): string {
        return match ($tokenType) {
            T_FUNCTION, T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM,
            T_EXTENDS, T_IMPLEMENTS, T_ABSTRACT, T_FINAL,
            T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY,
            T_STATIC, T_CONST, T_VAR, T_NEW, T_USE, T_NAMESPACE,
            T_RETURN, T_IF, T_ELSE, T_ELSEIF, T_SWITCH, T_CASE,
            T_DEFAULT, T_FOR, T_FOREACH, T_WHILE, T_DO, T_BREAK,
            T_CONTINUE, T_THROW, T_TRY, T_CATCH, T_FINALLY,
            T_MATCH, T_YIELD, T_YIELD_FROM, T_ECHO, T_PRINT,
            T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE,
            T_INSTANCEOF, T_CLONE, T_DECLARE, T_GLOBAL, T_ARRAY,
            T_CALLABLE, T_FN, T_AS => 'keyword',

            T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE => 'string',

            T_VARIABLE => 'variable',

            T_COMMENT, T_DOC_COMMENT => 'comment',

            T_LNUMBER, T_DNUMBER => 'number',

            T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_CLOSE_TAG => 'tag',

            T_STRING => 'identifier',

            default => 'token',
        };
    }
}
