<?php

declare(strict_types=1);

namespace Marko\DevTools\PhpCsFixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Token;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * Removes unnecessary curly braces from simple variable interpolation in strings.
 *
 * Before: "Hello {$name}"
 * After:  "Hello $name"
 *
 * Keeps curly braces when required for:
 * - Array access: {$array['key']}
 * - Object properties: {$obj->prop}
 * - Complex expressions: {$obj->method()}
 */
final class SimpleStringVariableFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'Marko/simple_string_variable';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Removes unnecessary curly braces from simple variable interpolation in strings.',
            [
                new CodeSample(
                    <<<'PHP'
<?php
$message = "Hello $name";
PHP,
                ),
            ],
        );
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(T_ENCAPSED_AND_WHITESPACE)
            || $tokens->isTokenKindFound(T_CONSTANT_ENCAPSED_STRING);
    }

    protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind([T_ENCAPSED_AND_WHITESPACE, T_CONSTANT_ENCAPSED_STRING])) {
                continue;
            }

            $content = $token->getContent();

            // Match {$simpleVar} but not {$array['key']} or {$obj->prop}
            // Only match simple variable names without any operators or brackets
            $newContent = preg_replace(
                '/\{\$([a-zA-Z_][a-zA-Z0-9_]*)\}/',
                '\$$1',
                $content,
            );

            if ($newContent !== $content) {
                $tokens[$index] = new Token([$token->getId(), $newContent]);
            }
        }
    }
}
