---
title: marko/inertia-react
description: React companion for marko/inertia - configuration defaults for React client and SSR entries.
---

React companion for [`marko/inertia`](/docs/packages/inertia/) and [`marko/vite`](/docs/packages/vite/). It ships Marko configuration defaults for React client and SSR entrypoints while leaving the JavaScript application code in your project.

## Installation

```bash
composer require marko/inertia-react
```

Install the matching frontend dependencies in your app:

```bash
npm install @inertiajs/react@^3.0 react@^19.0 react-dom@^19.0 @vitejs/plugin-react@^6.0 vite@^8.0
```

## Configuration

Configure via `config/inertia-react.php`:

```php title="config/inertia-react.php"
return [
    'clientEntry' => env('INERTIA_REACT_CLIENT_ENTRY', 'app/react-web/resources/js/app.jsx'),
    'ssrEntry' => env('INERTIA_REACT_SSR_ENTRY', 'app/react-web/resources/js/ssr.jsx'),
    'ssrBundle' => env('INERTIA_REACT_SSR_BUNDLE', 'bootstrap/ssr/react/ssr.js'),
];
```

| Key | Purpose |
| --- | --- |
| `clientEntry` | Vite entry used by browser-rendered Inertia responses. |
| `ssrEntry` | Vite entry used to build the React SSR server bundle. |
| `ssrBundle` | Relative path to the built SSR bundle loaded by your SSR runner. |

## Usage

Use the configured client entry when rendering React-backed Inertia pages:

```php
use Marko\Inertia\Inertia;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class DashboardController
{
    public function __construct(
        private readonly Inertia $inertia,
    ) {}

    public function index(Request $request): Response
    {
        return $this->inertia->render(
            request: $request,
            component: 'Dashboard',
            assetEntry: 'app/react-web/resources/js/app.jsx',
        );
    }
}
```

Create the client entry at `app/react-web/resources/js/app.jsx`:

```jsx title="app/react-web/resources/js/app.jsx"
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
    return pages[`./Pages/${name}.jsx`];
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
```

Create the SSR entry at `app/react-web/resources/js/ssr.jsx`:

```jsx title="app/react-web/resources/js/ssr.jsx"
import { createInertiaApp } from '@inertiajs/react';
import createServer from '@inertiajs/react/server';
import ReactDOMServer from 'react-dom/server';

createServer((page) =>
  createInertiaApp({
    page,
    render: ReactDOMServer.renderToString,
    resolve: (name) => {
      const pages = import.meta.glob('./Pages/**/*.jsx', { eager: true });
      return pages[`./Pages/${name}.jsx`];
    },
    setup: ({ App, props }) => <App {...props} />,
  }),
);
```

## API Reference

This package is configuration-only. It does not add PHP services or bindings; `module.php` is intentionally omitted because Marko can discover the package through Composer metadata and load its `config/` directory without explicit module options.

## Related Packages

- [`marko/inertia`](/docs/packages/inertia/) - renders Inertia responses and handles SSR fallback
- [`marko/vite`](/docs/packages/vite/) - resolves the configured React Vite entry
- [`marko/env`](/docs/packages/env/) - provides the `env()` helper used in `config/inertia-react.php`
