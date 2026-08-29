<?php

declare(strict_types=1);

namespace Marko\Tests\Support\Psr7Containment;

use PhpToken;

/**
 * Detects references to PSR-7 and RoadRunner symbols in a given set of files.
 *
 * `marko/core` has zero PSR-7 dependency and `spiral/roadrunner-http` /
 * `nyholm/psr7` are declared only in `packages/roadrunner`. This detector
 * enforces that no other package reaches for those symbols, so the PSR-7
 * boundary can never quietly leak into the rest of the monorepo.
 *
 * Tokenizes each file and matches only real code-level name tokens (`use`
 * imports, type hints, `Foo\Bar::class` references, instantiations, static
 * calls). A mention of one of these namespaces inside a comment or string
 * literal does not tokenize as a name token, so it is deliberately NOT
 * flagged — this scan enforces the dependency boundary, not the vocabulary
 * of docblocks or string literals.
 */
readonly class Psr7ContainmentDetector
{
    /**
     * @var list<string>
     */
    private const array CONFINED_PREFIXES = [
        'Psr\\Http\\Message',
        'Nyholm\\Psr7',
        'Spiral\\RoadRunner',
    ];

    /**
     * @param list<string> $files
     *
     * @return list<array{file: string, symbol: string}>
     */
    public function scan(array $files): array
    {
        $violations = [];

        foreach ($files as $file) {
            foreach ($this->confinedSymbolsIn($file) as $symbol) {
                $violations[] = ['file' => $file, 'symbol' => $symbol];
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function confinedSymbolsIn(string $file): array
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return [];
        }

        $symbols = [];

        foreach (PhpToken::tokenize($content) as $token) {
            $isNameToken = in_array(
                $token->id,
                [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE],
                true,
            );

            if (!$isNameToken) {
                continue;
            }

            $name = ltrim($token->text, '\\');

            foreach (self::CONFINED_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    $symbols[] = $token->text;
                }
            }
        }

        return $symbols;
    }
}
