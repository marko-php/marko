---
title: marko/docs-markdown
description: The canonical Marko documentation content as an installable Composer package.
---

The canonical Marko documentation content, shipped as an installable Composer package. Every page of the Marko docs lives under this package's `docs/` directory, and `MarkdownRepository` reads it. This is what makes the documentation **modular** — the docs are a module like everything else in Marko, so search drivers and the docs site read from one source of truth instead of a copy.

Two consumers read this content:

1. **The search drivers** (`marko/docs-fts`, `marko/docs-vec`) index the markdown to power documentation search.
2. **The marko.build Astro site** renders it. The site's content directory (`docs/src/content/docs`) is a symlink to this package's `docs/` directory, so there is exactly one copy of every page.

## Installation

```bash
composer require marko/docs-markdown
```

Usually installed automatically as a dependency of a search driver (`marko/docs-fts` or `marko/docs-vec`).

## Usage

Inject `MarkdownRepository` to read documentation content:

```php
use Marko\DocsMarkdown\MarkdownRepository;

class DocsTool
{
    public function __construct(
        private MarkdownRepository $repo,
    ) {}

    public function dump(): void
    {
        foreach ($repo->listAllPages() as $page) {
            echo $page;  // page id, e.g. "getting-started/installation"
        }

        $raw = $this->repo->getRawMarkdown('getting-started/installation');
    }
}
```

## API Reference

| Method | Returns | Description |
|--------|---------|-------------|
| `listAllPages()` | `list<string>` | All page ids (relative paths without extension) |
| `getRawMarkdown(string $id)` | `string` | Raw markdown for a page id |
| `getDocsPath()` | `string` | Absolute path to the package's `docs/` root |

## Related Packages

- [`marko/docs`](/docs/packages/docs/) — the search contract
- [`marko/docs-fts`](/docs/packages/docs-fts/) — lexical search driver that indexes this content
- [`marko/docs-vec`](/docs/packages/docs-vec/) — hybrid semantic search driver that indexes this content
