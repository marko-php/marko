# marko/blog

WordPress-like blog functionality for Marko --- posts, authors, categories, tags, and threaded comments with email verification.

## Installation

```bash
composer require marko/blog
```

**Required:** A view driver (e.g., `marko/view-latte`) and a database driver (e.g., `marko/database-mysql`):

```bash
composer require marko/blog marko/view-latte marko/database-mysql
```

## Quick Example

Once installed with a view and database driver, the blog works automatically:

1. Run migrations to create tables: `marko db:migrate`
2. Visit `/blog` to see the post list
3. Visit `/blog/{slug}` to view a single post

## Documentation

Full usage, configuration, events, API reference, and examples: [marko/blog](https://marko.build/docs/packages/blog/)
