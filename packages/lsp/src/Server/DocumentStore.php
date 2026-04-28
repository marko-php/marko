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
    ): string
    {
        $text = $this->get($uri);

        if ($text === null) {
            return '';
        }

        $lines = explode("\n", $text);

        return $lines[$line] ?? '';
    }
}
