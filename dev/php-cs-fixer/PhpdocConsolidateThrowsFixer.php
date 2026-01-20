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
 * Consolidates multiple @throws tags|ContainerExceptionInterface|NotFoundExceptionInterface
 *
 * Before:
 *
 * After:
 */
final class PhpdocConsolidateThrowsFixer extends AbstractFixer
{
    public function getName(): string
    {
        return 'Marko/phpdoc_consolidate_throws';
    }

    public function getDefinition(): FixerDefinitionInterface
    {
        return new FixerDefinition(
            'Consolidates multiple @throws tags into a single tag with pipe-delimited exceptions.',
            [
                new CodeSample(
                    <<<'PHP'
<?php
/**
 * @throws ContainerExceptionInterface
 * @throws NotFoundExceptionInterface
 */
function foo() {}
PHP,
                ),
            ],
        );
    }

    public function isCandidate(
        Tokens $tokens,
    ): bool {
        return $tokens->isTokenKindFound(T_DOC_COMMENT);
    }

    protected function applyFix(
        SplFileInfo $file,
        Tokens $tokens,
    ): void {
        foreach ($tokens as $index => $token) {
            if (!$token->isGivenKind(T_DOC_COMMENT)) {
                continue;
            }

            $content = $token->getContent();
            $newContent = $this->consolidateThrows($content);

            if ($newContent !== $content) {
                $tokens[$index] = new Token([T_DOC_COMMENT, $newContent]);
            }
        }
    }

    private function consolidateThrows(
        string $docblock,
    ): string {
        // Match all @throws tags with their exception types (stop at whitespace, *, or end of line)
        $pattern = '/@throws\s+([^\s*]+)/';

        if (!preg_match_all($pattern, $docblock, $matches)) {
            return $docblock;
        }

        $exceptions = $matches[1];

        // If only one @throws tag, nothing to consolidate
        if (count($exceptions) <= 1) {
            return $docblock;
        }

        // Flatten any existing pipe-delimited exceptions
        $allExceptions = [];
        foreach ($exceptions as $exception) {
            $parts = explode('|', $exception);
            foreach ($parts as $part) {
                $part = trim($part);
                if ($part !== '' && !in_array($part, $allExceptions, true)) {
                    $allExceptions[] = $part;
                }
            }
        }

        // Create the consolidated @throws tag (no description - exception names should be self-explanatory)
        $consolidated = '@throws ' . implode('|', $allExceptions);

        // Remove all @throws lines except the first one
        $lines = explode("\n", $docblock);
        $newLines = [];
        $firstThrowsFound = false;

        foreach ($lines as $line) {
            if (preg_match('/@throws\s+/', $line)) {
                if (!$firstThrowsFound) {
                    // Replace the entire @throws line with the consolidated version (removes any description)
                    $newLines[] = preg_replace('/@throws\s+.+/', $consolidated, $line);
                    $firstThrowsFound = true;
                }
                // Skip subsequent @throws lines
            } else {
                $newLines[] = $line;
            }
        }

        return implode("\n", $newLines);
    }
}
