# marko/docs-markdown

Canonical Marko documentation as a Composer package — provides `MarkdownRepository` for content access by docs search drivers.

## Overview

`marko/docs-markdown` ships the raw Markdown source of the Marko documentation tree. It is not a search package; it is a data package. The `MarkdownRepository` class exposes the doc files so that a driver package (`marko/docs-fts`) can index them without bundling its own copy of the content. This ensures the search index always reflects the same canonical documentation.

## Installation

```bash
composer require marko/docs-markdown
```

The docs search driver (`marko/docs-fts`) requires this automatically — you typically do not need to install it directly.

## Usage

```php
use Marko\DocsMarkdown\MarkdownRepository;

$repo = $container->get(MarkdownRepository::class);

foreach ($repo->all() as $doc) {
    echo $doc->path;    // Relative path, e.g. "getting-started/installation.md"
    echo $doc->title;   // Extracted from first H1
    echo $doc->content; // Raw Markdown content
}

$doc = $repo->find('getting-started/installation');
```

## API Reference

- `MarkdownRepository::all()` — Return all documentation files as `MarkdownDoc[]`
- `MarkdownRepository::find(string $path)` — Return a single doc by path (without `.md` extension)
- `MarkdownDoc::$path` — Relative path within the docs tree
- `MarkdownDoc::$title` — Title extracted from first H1 heading
- `MarkdownDoc::$content` — Full Markdown content

## Documentation

Full usage: [marko/docs-markdown](https://marko.build/docs/packages/docs-markdown/)
