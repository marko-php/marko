<?php

declare(strict_types=1);

namespace Marko\DocsFts;

class FtsQueryBuilder
{
    /**
     * Common English stop words plus the FTS5 boolean operator keywords
     * (`and`/`or`/`not`). Dropped to improve precision. `near` is intentionally
     * absent — it is neutralized by quoting instead, so a real search term like
     * "near" is preserved.
     */
    private const array STOP_WORDS = [
        'a', 'an', 'and', 'are', 'as', 'at', 'be', 'but', 'by', 'can', 'did',
        'do', 'does', 'for', 'from', 'how', 'i', 'in', 'is', 'it', 'me', 'my',
        'no', 'not', 'of', 'on', 'or', 'that', 'the', 'this', 'to', 'was', 'were',
        'what', 'when', 'where', 'which', 'why', 'with', 'you', 'your',
    ];

    /**
     * Convert a natural-language query into a safe FTS5 MATCH expression.
     *
     * Raw user input cannot be handed to FTS5 directly: apostrophes and quotes are
     * parsed as query syntax (raising "fts5: syntax error"), and FTS5's default
     * implicit-AND across every token tanks recall on full questions ("how do
     * observers react to events" requires *every* word in one document).
     *
     * This tokenizes to alphanumeric words (dropping punctuation entirely), removes
     * stop words, quotes each remaining term (neutralizing FTS5 keyword operators),
     * and OR-joins them so BM25 ranks by term overlap. Returns '' when no usable
     * term remains, so the caller can short-circuit to an empty result set.
     */
    public static function toMatchExpression(string $raw): string
    {
        $tokens = self::tokenize($raw);

        if ($tokens === []) {
            return '';
        }

        $meaningful = array_values(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) > 1
                && !in_array($token, self::STOP_WORDS, true),
        ));

        // If filtering removed everything (e.g. an all-stop-word question), fall
        // back to the raw tokens so the search still looks for something.
        $terms = $meaningful === [] ? $tokens : $meaningful;

        return implode(' OR ', array_map(
            static fn (string $term): string => '"' . $term . '"',
            $terms,
        ));
    }

    /** @return list<string> */
    private static function tokenize(string $raw): array
    {
        preg_match_all('/[\p{L}\p{N}]+/u', mb_strtolower($raw), $matches);

        return $matches[0];
    }
}
