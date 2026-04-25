# Marko Inertia React

Configuration defaults for React apps built with `marko/inertia` and `marko/vite`.

## Install

```bash
composer require marko/inertia marko/inertia-react marko/vite
npm install @inertiajs/react@^3.0 react@^19.0 react-dom@^19.0 @vitejs/plugin-react@^6.0 vite@^8.0
```

## Files

Create the client entry at `app/react-web/resources/js/app.jsx`:

```jsx
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

```jsx
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

## Configuration

The package exposes:

- `clientEntry`: `app/react-web/resources/js/app.jsx`
- `ssrEntry`: `app/react-web/resources/js/ssr.jsx`
- `ssrBundle`: `bootstrap/ssr/react/ssr.js`

Override them with `INERTIA_REACT_CLIENT_ENTRY`, `INERTIA_REACT_SSR_ENTRY`, and `INERTIA_REACT_SSR_BUNDLE`.
