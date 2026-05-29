# marko.build docs site

The [marko.build/docs](https://marko.build/docs/) documentation site, built with [Astro](https://astro.build/) + [Starlight](https://starlight.astro.build/).

## Content lives in `marko/docs-markdown`

The documentation content is **not** stored in this directory. It lives in the `marko/docs-markdown` package so the docs are a first-class Composer module — the same content the AI search drivers (`marko/docs-fts`, `marko/docs-vec`) index is what this site renders. One source of truth, not a copy.

`src/content/docs` is a **symlink** into that package:

```
docs/src/content/docs  →  ../../../packages/docs-markdown/docs
```

To edit a docs page, edit the file under `packages/docs-markdown/docs/` (or through the symlink — they are the same file).

### Windows: enable symlink support

Git symlinks work out of the box on macOS and Linux. On Windows, enable symlink support **before** cloning (or re-checkout after enabling):

```bash
git config --global core.symlinks true
```

You also need [Developer Mode](https://learn.microsoft.com/windows/apps/get-started/enable-your-device-for-development) enabled (or run git as administrator) so Windows permits symlink creation. With that set, `src/content/docs` resolves to the package content and the site builds normally.

> This affects **only** local builds of this website on Windows. The AI tooling (`codeindexer`, `docs-fts`, `docs-vec`, `mcp`, `lsp`) reads the package's real `docs/` directory directly — never the symlink — so it works identically on every platform regardless of this setting.

## Commands

All commands are run from the `docs/` directory:

| Command | Action |
| :------ | :----- |
| `npm install` | Install dependencies |
| `npm run dev` | Start local dev server at `localhost:4321` |
| `npm run build` | Build the production site to `./dist/` |
| `npm run preview` | Preview the build locally before deploying |

## Structure

```
docs/
├── src/
│   ├── assets/            # Images and static assets (live here, not in the package)
│   ├── components/        # Astro components
│   ├── content/
│   │   └── docs  ───────▶ symlink to packages/docs-markdown/docs
│   └── content.config.ts
├── astro.config.mjs
└── package.json
```

## Learn more

[Starlight docs](https://starlight.astro.build/) · [Astro docs](https://docs.astro.build)
