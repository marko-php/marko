<?php

declare(strict_types=1);

namespace Marko\DevTools\PhpCsFixer;

use PhpCsFixer\AbstractFixer;
use PhpCsFixer\FixerDefinition\CodeSample;
use PhpCsFixer\FixerDefinition\FixerDefinition;
use PhpCsFixer\FixerDefinition\FixerDefinitionInterface;
use PhpCsFixer\Tokenizer\Tokens;
use SplFileInfo;

/**
 * Removes unnecessary curly braces from simple variable interpolation in strings.
 *
 * Before: "Hello {$name}" / "Hello {$user->name}"
 * After:  "Hello $name"   / "Hello $user->name"
 *
 * Keeps curly braces when required for:
 * - Array access with string keys: {$array['key']}
 * - Method calls: {$obj->method()}
 * - Complex expressions: {$obj->prop->nested}
 */
final class RemoveUnnecessaryCurlyBracesFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'Marko/remove_unnecessary_curly_braces';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Removes unnecessary curly braces from simple variable interpolation in strings.',
            [
                new CodeSample(
                    <<<'PHP'
<?php
$message = "Hello {$name}";
PHP,
                ),
            ],
        );
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(T_CURLY_OPEN);
    }

    protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        for ($index = $tokens->count() - 1; $index > 0; --$index) {
            $token = $tokens[$index];

            // Look for T_CURLY_OPEN which starts {$var} in strings
            if (!$token->isGivenKind(T_CURLY_OPEN)) {
                continue;
            }

            // Next token should be T_VARIABLE
            $varIndex = $tokens->getNextMeaningfulToken($index);
            if ($varIndex === null || !$tokens[$varIndex]->isGivenKind(T_VARIABLE)) {
                continue;
            }

            // Check what comes after the variable
            $afterVarIndex = $tokens->getNextMeaningfulToken($varIndex);
            if ($afterVarIndex === null) {
                continue;
            }

            $closeIndex = null;

            // Case 1: Simple variable {$var}
            if ($tokens[$afterVarIndex]->getContent() === '}') {
                $closeIndex = $afterVarIndex;
            }

            // Case 2: Simple property access {$obj->prop}
            if ($tokens[$afterVarIndex]->isGivenKind(T_OBJECT_OPERATOR)) {
                $propIndex = $tokens->getNextMeaningfulToken($afterVarIndex);
                if ($propIndex !== null && $tokens[$propIndex]->isGivenKind(T_STRING)) {
                    $afterPropIndex = $tokens->getNextMeaningfulToken($propIndex);
                    // Must be closing brace (not method call or chained access)
                    if ($afterPropIndex !== null && $tokens[$afterPropIndex]->getContent() === '}') {
                        $closeIndex = $afterPropIndex;
                    }
                }
            }

            // If we didn't find a valid simple pattern, skip
            if ($closeIndex === null) {
                continue;
            }

            // Remove the closing brace
            $tokens->clearAt($closeIndex);

            // Remove the opening curly brace
            $tokens->clearAt($index);
        }
    }
}
