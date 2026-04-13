# marko/vite

Bundle and serve frontend assets in Marko applications with a Vite-first workflow that fits Marko's module structure in both development and production.

## Installation

```bash
composer require marko/vite
```

## Scaffold A Frontend

`marko/vite` provides the shared frontend scaffold command for Marko applications:

```bash
marko vite:init
```

You can also scaffold companion packages at the same time:

```bash
marko vite:init --tailwind
marko vite:init --inertia=vue
marko vite:init --inertia=react --tailwind
marko vite:init --inertia=svelte --tailwind
```

If you pass `--inertia=...` or `--tailwind` and the companion Marko package is not installed yet, `vite:init` installs it first and then continues the scaffold.

The scaffold updates the project with the minimum Vite setup for the selected stack:

- `package.json`
- `vite.config.ts`
- `resources/js/app.ts`

Preview the changes first:

```bash
marko vite:init --dry-run
```

Replace existing generated files:

```bash
marko vite:init --force
```

## What Gets Generated

The generated `vite.config.ts` uses Marko's shared base config:

```ts
import { defineConfig } from 'vite';
import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';

export default defineConfig(
  createBaseConfig({
    entrypoints: ['resources/js/app.ts'],
  }),
);
```

By default the generated `package.json` includes:

```json
{
  "scripts": {
    "dev": "vite --config ./vite.config.ts",
    "build": "vite build --config ./vite.config.ts"
  }
}
```

## Development And Production

Start the frontend dev server:

```bash
npm run dev
```

Or run it through Marko's dev orchestration:

```bash
marko dev:up
```

Build production assets:

```bash
npm run build
```

In development, Marko uses the configured Vite dev server when it is available. In production, Marko reads the Vite manifest from `public/build/manifest.json`.

## Configuration

Default configuration lives in `config/vite.php`:

```php
return [
    'dev_server_url' => 'http://localhost:5173',
    'dev_process_file_path' => '.marko/dev.json',
    'hot_file_path' => 'public/hot',
    'manifest_path' => 'public/build/manifest.json',
    'build_directory' => '/build',
    'assets_base_url' => '',
    'default_entrypoints' => [],
    'root_entrypoint_path' => 'resources/js/app.ts',
    'root_vite_config_path' => 'vite.config.ts',
];
```

## Render Assets In PHP

Render tags with the Vite manager:

```php
use Marko\Vite\Contracts\ViteManagerInterface;

$vite = $container->get(ViteManagerInterface::class);

echo $vite->tags('resources/js/app.ts');
```

Render the combined tags inside `<head>`:

```php
<?php

use Marko\Vite\Contracts\ViteManagerInterface;

/** @var ViteManagerInterface $vite */
$vite = $container->get(ViteManagerInterface::class);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Marko App', ENT_QUOTES, 'UTF-8') ?></title>
    <?= $vite->tags('resources/js/app.ts') ?>
</head>
<body>
    <?= $content ?>
</body>
</html>
```

Render styles and scripts separately when needed:

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Marko App', ENT_QUOTES, 'UTF-8') ?></title>
    <?= $vite->styles('resources/js/app.ts') ?>
</head>
<body>
    <?= $content ?>
    <?= $vite->scripts('resources/js/app.ts') ?>
</body>
</html>
```

If you configure `default_entrypoints`, you can omit the argument:

```php
echo $vite->tags();
```

## Marko Aliases

`createBaseConfig()` defines aliases that follow Marko's filesystem conventions:

- `@/` maps to `resources/js`
- `@module/...` maps to a discovered module `resources/js` directory

Examples:

```ts
import AppLayout from '@/layouts/AppLayout.vue';
import ModuleCard from '@blog/components/ModuleCard.vue';
```

Marko module aliases are discovered from:

1. `app/*/resources/js`
2. `modules/**/resources/js`

If the same module name exists in both places, `app/*` wins.

Vendor packages are not part of the `@module` alias space.

## Discovery Chain

Marko's generated frontend scaffolds follow a consistent discovery chain.

For JS aliases:

1. `resources/js`
2. `app/*/resources/js`
3. `modules/**/resources/js`

For typical Inertia page discovery in generated app files:

1. `resources/js/pages`
2. `app/*/resources/js/pages`
3. `modules/**/resources/js/pages`
4. `vendor/marko/**/resources/js/pages`

For typical server-layout discovery in generated app files:

1. `resources/js/layouts`
2. `app/*/resources/js/layouts`
3. `modules/**/resources/js/layouts`
4. `vendor/marko/**/resources/js/layouts`

The vendor paths are included for framework-owned frontend resources. They are not exposed as `@module` aliases.

## Customizing Vite

The generated `vite.config.ts` is just a normal Vite config. Add plugins, extra entrypoints, or additional Vite options through `createBaseConfig()`:

```ts
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';

export default defineConfig(
  createBaseConfig({
    plugins: [vue(), tailwindcss()],
    entrypoints: ['resources/js/app.ts', 'resources/css/app.css'],
    config: {
      server: {
        port: 5174,
      },
    },
  }),
);
```

`createBaseConfig()` also applies Marko-friendly development defaults, including ignoring runtime-write directories such as `storage/`, `.marko/`, and `public/build/` so dev reloads stay stable.

## Production And CI

A minimal CI pipeline looks like:

```bash
composer install --no-interaction --prefer-dist
npm ci
npm run build
```

If your deploy pipeline runs tests, build frontend assets before browser, HTTP, or integration checks that expect `public/build/manifest.json` to exist.

## Extending And Overriding

`marko/vite` can be customized through Marko's module system.

### Swap an interface binding

```php
<?php

declare(strict_types=1);

use App\MyApp\Vite\CustomTagRenderer;
use Marko\Vite\Contracts\TagRendererInterface;

return [
    'bindings' => [
        TagRendererInterface::class => CustomTagRenderer::class,
    ],
];
```

### Intercept public methods with a plugin

```php
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Vite\Contracts\ViteManagerInterface;

#[Plugin(target: ViteManagerInterface::class)]
class ViteManagerPlugin
{
    #[Before(method: 'tags')]
    public function addAdminEntrypoint(string|array|null $entrypoints = null): array
    {
        $entrypoints = is_array($entrypoints) ? $entrypoints : array_filter([$entrypoints]);
        $entrypoints[] = 'resources/js/admin.ts';

        return [array_values(array_unique($entrypoints))];
    }
}
```

### Replace a concrete class with a preference

```php
<?php

declare(strict_types=1);

namespace App\MyApp\Vite;

use Marko\Core\Attributes\Preference;
use Marko\Vite\TagRenderer;

#[Preference(replaces: TagRenderer::class)]
class CustomTagRenderer extends TagRenderer
{
    // Override selected methods here.
}
```

## Related Packages

- [`marko/inertia`](../inertia/README.md) for server-side Inertia rendering in Marko
- [`marko/inertia-vue`](../inertia-vue/README.md) for the Vue adapter scaffold
- [`marko/inertia-react`](../inertia-react/README.md) for the React adapter scaffold
- [`marko/inertia-svelte`](../inertia-svelte/README.md) for the Svelte adapter scaffold

## Documentation

Full usage, configuration, and API reference: [marko/vite](https://marko.build/docs/packages/vite/)
