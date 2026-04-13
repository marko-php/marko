# marko/tailwindcss

Add Tailwind CSS to a Marko application with the standard Vite pipeline, published starter assets, and content path discovery that works across app and package views.

## Installation

```bash
composer require marko/tailwindcss
```

`marko/tailwindcss` builds on top of `marko/vite`, so the package depends on the Vite workflow and bootstraps it for you.

## Initialize Project Files

```bash
marko vite:init --tailwind
npm install
```

This is the primary Tailwind scaffold workflow and prepares the minimum Tailwind + Vite setup:

- creates or updates `package.json`
- publishes or updates `vite.config.ts`
- publishes `resources/js/app.ts` when missing
- publishes `resources/css/app.css`
- adds the required Tailwind dev dependencies

You can preview changes first with:

```bash
marko vite:init --tailwind --dry-run
```

Replace generated files with:

```bash
marko vite:init --tailwind --force
```

## Configuration

Default configuration lives in `config/tailwindcss.php`:

```php
return [
    'enabled' => true,
    'entrypoints' => [
        'css' => 'resources/css/app.css',
    ],
    'auto_include_with_vite' => true,
    'content_paths' => [
        'app/**/*.php',
        'modules/**/*.php',
        'resources/js/**/*.{js,jsx,ts,tsx,vue,svelte}',
        'resources/views/**/*.latte',
        'vendor/marko/**/resources/views/**/*.latte',
    ],
    'extra_content_paths' => [],
    'content_path_providers' => [],
];
```

The generated CSS entrypoint is intentionally small:

```css
@import "tailwindcss";
```

The generated `vite.config.ts` includes both the Tailwind Vite plugin and the CSS entrypoint:

```ts
import tailwindcss from '@tailwindcss/vite';
import { defineConfig } from 'vite';
import { createBaseConfig } from './vendor/marko/vite/resources/config/createViteConfig';

export default defineConfig(
  createBaseConfig({
    plugins: [tailwindcss()],
    entrypoints: ['resources/js/app.ts', 'resources/css/app.css'],
  }),
);
```

## Usage

Start frontend development through Marko's dev orchestrator:

```bash
marko dev:up
```

`marko dev:up` picks up the configured frontend dev process and runs the `package.json` `dev` script, which in turn starts Vite with Tailwind support.

If you want to run the underlying script directly, it is:

```bash
npm run dev
```

Build production assets:

```bash
npm run build
```

If `tailwindcss.auto_include_with_vite` is `true`, Tailwind automatically plugs into `ViteManagerInterface` and adds the configured CSS entrypoint when you render Vite tags:

```php
use Marko\Vite\Contracts\ViteManagerInterface;

$vite = $container->get(ViteManagerInterface::class);

echo $vite->tags('resources/js/app.ts');
```

If you want to manage CSS entrypoints manually, disable auto-inclusion:

```php
return [
    'auto_include_with_vite' => false,
];
```

Then render the CSS entrypoint explicitly in your Vite config and view layer as part of your chosen asset strategy.

## Production And CI

For production builds, run the generated build script:

```bash
npm run build
```

This runs Vite in production mode using your generated `vite.config.ts`, which includes Tailwind support and emits compiled assets plus the Vite manifest under `public/build`.

A minimal CI pipeline looks like:

```bash
composer install --no-interaction --prefer-dist
npm ci
npm run build
```

If your pipeline runs integration or rendering tests against production assets, make sure the frontend build happens first so `public/build/manifest.json` is available.

## Extending And Overriding

`marko/tailwindcss` exposes both interface bindings and plugin-based extension points.

### Swap an interface binding

Replace the default content path provider:

```php
<?php

declare(strict_types=1);

use App\MyApp\Tailwind\CustomContentPathProvider;
use Marko\TailwindCss\Contracts\ContentPathProviderInterface;

return [
    'bindings' => [
        ContentPathProviderInterface::class => CustomContentPathProvider::class,
    ],
];
```

You can also replace `TailwindEntrypointProviderInterface` or `TailwindPublisherInterface` the same way.

### Add extra content paths from your own provider

Register provider classes in config when you want package-specific discovery logic:

```php
return [
    'content_path_providers' => [
        App\MyApp\Tailwind\AdminContentPathProvider::class,
    ],
    'extra_content_paths' => [
        'themes/**/*.php',
    ],
];
```

Each provider must implement `ContentPathProviderInterface`.

### Intercept Vite behavior with a plugin

This package already does this internally by targeting `ViteManagerInterface`. You can layer on your own plugin too:

```php
<?php

declare(strict_types=1);

namespace App\MyApp\Plugin;

use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Vite\Contracts\ViteManagerInterface;

#[Plugin(target: ViteManagerInterface::class)]
class AdminThemeAssetsPlugin
{
    #[Before(method: 'tags')]
    public function addThemeAssets(string|array|null $entrypoints = null): array
    {
        $entrypoints = is_array($entrypoints) ? $entrypoints : array_filter([$entrypoints]);
        $entrypoints[] = 'resources/css/admin-theme.css';

        return [array_values(array_unique($entrypoints))];
    }
}
```

### Replace a concrete class with a preference

If you need inheritance-based overrides instead of interface swapping:

```php
<?php

declare(strict_types=1);

namespace App\MyApp\Tailwind;

use Marko\Core\Attributes\Preference;
use Marko\TailwindCss\DefaultContentPathProvider;

#[Preference(replaces: DefaultContentPathProvider::class)]
class CustomContentPathProvider extends DefaultContentPathProvider
{
    // Override selected methods here.
}
```

## Documentation

Full usage, configuration, and API reference: [marko/tailwindcss](https://marko.build/docs/packages/tailwindcss/)
