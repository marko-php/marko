---
title: marko/inertia-react
description: React companion for marko/inertia - configuration defaults and a frontend marker binding for React.
---

React companion for [`marko/inertia`](/docs/packages/inertia/) and [`marko/vite`](/docs/packages/vite/). It overlays the parent Inertia configuration with React defaults and registers a frontend marker binding so installing multiple Inertia frontend companions fails loudly.

## Installation

```bash
composer require marko/inertia-react
```

Install the matching frontend dependencies in your app:

```bash
npm install @inertiajs/react react react-dom @vitejs/plugin-react vite
```

Refer to the [Inertia.js docs](https://inertiajs.com/) for currently supported versions of each frontend adapter.

## Configuration

This package contributes defaults to the parent `config/inertia.php` namespace:

```php title="packages/inertia-react/config/inertia.php"
return [
    'assetEntry' => env('INERTIA_REACT_CLIENT_ENTRY', 'app/react-web/resources/js/app.jsx'),
];
```

| Key | Purpose |
| --- | --- |
| `assetEntry` | Vite entry used by browser-rendered Inertia responses. |

## Usage

Render React-backed Inertia pages without passing an asset entry; `marko/inertia` reads it from configuration:

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

This package registers `Marko\Inertia\Frontend\InertiaFrontendInterface` to a React marker implementation. Installing more than one Inertia frontend companion produces the same binding conflict protection used by Marko driver siblings.

## Related Packages

- [`marko/inertia`](/docs/packages/inertia/) - renders Inertia responses and handles SSR fallback
- [`marko/vite`](/docs/packages/vite/) - resolves the configured React Vite entry
- [`marko/env`](/docs/packages/env/) - provides the `env()` helper used in `config/inertia.php`
