<?php

declare(strict_types=1);

namespace Marko\Lsp\Server;

class DocumentStore
{
    /** @var array<string, string> uri => text */
    private array $documents = [];

    public function open(
        string $uri,
        string $text,
    ): void
    {
        $this->documents[$uri] = $text;
    }

    public function update(
        string $uri,
        string $text,
    ): void
    {
        $this->documents[$uri] = $text;
    }

    public function close(string $uri): void
    {
        unset($this->documents[$uri]);
    }

    public function get(string $uri): ?string
    {
        return $this->documents[$uri] ?? null;
    }

    /**
     * Extract the line at the given 0-indexed line number, or empty string if out of range.
     */
    public function lineAt(
        string $uri,
        int $line,
    ): string {
        $text = $this->get($uri);

        if ($text === null) {
            return '';
        }

        $lines = explode("\n", $text);

        return $lines[$line] ?? '';
    }

    /**
     * Extract the quoted string the cursor is inside on the given line. Returns null
     * if the cursor is not within a single- or double-quoted string literal.
     */
    public function quotedStringAt(
        string $uri,
        int $line,
        int $col,
    ): ?string {
        $lineText = $this->lineAt($uri, $line);

        if ($lineText === '' || $col < 0 || $col > strlen($lineText)) {
            return null;
        }

        $prefix = substr($lineText, 0, $col);
        $suffix = substr($lineText, $col);

        if (preg_match('/[\'"]([^\'"]*)$/', $prefix, $pre) !== 1) {
            return null;
        }

        if (preg_match('/^([^\'"]*)[\'"]/', $suffix, $post) !== 1) {
            return null;
        }

        return $pre[1] . $post[1];
    }
}
