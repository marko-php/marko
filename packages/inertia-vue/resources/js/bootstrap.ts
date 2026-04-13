import { createInertiaApp } from "@inertiajs/vue3";
import { createApp, h, type App as VueApp } from "vue";
import {
  parseMarkoPageComponent,
  resolveMarkoPageComponent,
  createMarkoTitleResolver,
  type MarkoInertiaPages,
} from "../../../inertia/resources/js/client";

type MarkoInertiaVuePage = {
  layout?: unknown;
  [key: string]: unknown;
};

type MarkoInertiaPagePayload = {
  component?: string;
  props?: {
    _marko?: {
      layout?: {
        name?: string;
        component?: string;
      };
    };
    [key: string]: unknown;
  };
  [key: string]: unknown;
};

type SetupContext = {
  app: VueApp;
  el: Element;
  App: unknown;
  props: Record<string, unknown>;
  plugin: unknown;
};

type MarkoInertiaVueConfig = {
  id: string;
  resolve: ReturnType<typeof createMarkoPageResolver>;
  title: ReturnType<typeof createMarkoTitleResolver>;
  setup: (context: {
    el: Element;
    App: unknown;
    props: Record<string, unknown>;
    plugin: unknown;
  }) => void;
  [key: string]: unknown;
};

export type MarkoInertiaVueOptions = {
  pages: MarkoInertiaPages;
  id?: string;
  title?: (title: string, appName: string) => string;
  defaultLayout?: unknown;
  resolveLayout?: (context: {
    component: string;
    moduleName: string;
    componentPath: string;
    page: MarkoInertiaPagePayload;
  }) => unknown;
  serverLayouts?: Record<string, unknown>;
  resolveServerLayout?: (layout: {
    name?: string;
    component?: string;
  }, context: {
    component: string;
    moduleName: string;
    componentPath: string;
    page: MarkoInertiaPagePayload;
  }) => unknown;
  setup?: (context: SetupContext) => void;
  inertia?:
    | Partial<MarkoInertiaVueConfig>
    | ((config: MarkoInertiaVueConfig) => MarkoInertiaVueConfig);
};

export function discoverMarkoServerLayouts(
  layouts: Record<string, unknown>,
): Record<string, unknown> {
  const discoveredEntries = Object.entries(layouts).sort(([leftPath], [rightPath]) =>
    layoutDiscoveryPriority(leftPath) - layoutDiscoveryPriority(rightPath),
  );

  return Object.fromEntries(
    discoveredEntries.flatMap(([path, module]) => {
      const entry = parseDiscoveredLayout(path);

      if (entry === null) {
        return [];
      }

      const component = normalizeDiscoveredLayoutModule(module);

      return [
        [entry.name, component],
        [entry.className, component],
      ];
    }),
  );
}

export function bootstrapMarkoInertiaVue(
  options: MarkoInertiaVueOptions,
): Promise<unknown> {
  const config: MarkoInertiaVueConfig = {
    id: options.id ?? "app",
    resolve: async (component, page) => {
      const resolved = await resolveMarkoPageComponent(component, options.pages);
      const normalizedComponent = normalizeVuePage(resolved);
      const normalizedPage = normalizePagePayload(page);
      const parsed = parseMarkoPageComponent(component);

      applyResolvedLayout(
        normalizedComponent,
        resolveLayout(options, normalizedPage, component, parsed),
      );

      return resolved;
    },
    title: createMarkoTitleResolver(options.title),
    setup: ({ el, App, props, plugin }) => {
      const app = createApp({
        render: () => h(App as never, props),
      });

      app.use(plugin as never);

      if (options.setup) {
        options.setup({
          app,
          el,
          App,
          props: props as Record<string, unknown>,
          plugin,
        });

        return;
      }

      app.mount(el);
    },
  };

  const inertiaConfig =
    typeof options.inertia === "function"
      ? options.inertia(config)
      : {
          ...config,
          ...options.inertia,
        };

  return createInertiaApp(inertiaConfig as never);
}

function normalizeVuePage(
  resolved: unknown,
): MarkoInertiaVuePage {
  if (isObjectLike(resolved) && "default" in resolved && isObjectLike(resolved.default)) {
    return resolved.default as MarkoInertiaVuePage;
  }

  if (isObjectLike(resolved)) {
    return resolved as MarkoInertiaVuePage;
  }

  throw new Error(
    "Resolved Inertia Vue pages must export a component object or a module with a default export.",
  );
}

function applyResolvedLayout(
  pageComponent: MarkoInertiaVuePage,
  layout: unknown,
): void {
  if (layout === undefined || pageComponent.layout !== undefined) {
    return;
  }

  pageComponent.layout = layout;
}

function isObjectLike(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function normalizePagePayload(page: unknown): MarkoInertiaPagePayload {
  return isObjectLike(page) ? (page as MarkoInertiaPagePayload) : {};
}

function resolveLayout(
  options: MarkoInertiaVueOptions,
  page: MarkoInertiaPagePayload,
  component: string,
  parsed: ReturnType<typeof parseMarkoPageComponent>,
): unknown {
  return (
    resolveServerLayout(options, page, component, parsed) ??
    options.resolveLayout?.({
      component,
      moduleName: parsed.moduleName,
      componentPath: parsed.componentPath,
      page,
    }) ??
    options.defaultLayout
  );
}

function resolveServerLayout(
  options: MarkoInertiaVueOptions,
  page: MarkoInertiaPagePayload,
  component: string,
  parsed: ReturnType<typeof parseMarkoPageComponent>,
): unknown {
  const layout = page.props?._marko?.layout;

  if (!isObjectLike(layout)) {
    return undefined;
  }

  return (
    options.resolveServerLayout?.(layout, {
      component,
      moduleName: parsed.moduleName,
      componentPath: parsed.componentPath,
      page,
    }) ??
    resolveServerLayoutFromMap(layout, options.serverLayouts)
  );
}

function resolveServerLayoutFromMap(
  layout: {
    name?: string;
    component?: string;
  },
  serverLayouts?: Record<string, unknown>,
): unknown {
  if (serverLayouts == null) {
    return undefined;
  }

  if (isNonEmptyString(layout.name) && layout.name in serverLayouts) {
    return serverLayouts[layout.name];
  }

  if (isNonEmptyString(layout.component) && layout.component in serverLayouts) {
    return serverLayouts[layout.component];
  }

  return undefined;
}

function isNonEmptyString(value: unknown): value is string {
  return typeof value === "string" && value !== "";
}

function parseDiscoveredLayout(
  path: string,
): { name: string; className: string } | null {
  const normalizedPath = path.replaceAll("\\", "/");
  const fileName = normalizedPath.split("/").pop();

  if (fileName === undefined) {
    return null;
  }

  const className = fileName.replace(/\.[^.]+$/, "");
  const moduleName = resolveDiscoveredLayoutModuleName(normalizedPath);
  const name = moduleName === null ? className : `${moduleName}::${className}`;

  return {
    name,
    className,
  };
}

function resolveDiscoveredLayoutModuleName(
  path: string,
): string | null {
  const appMatch = path.match(/\/app\/([^/]+)\/resources\/js\/layouts\//);

  if (appMatch !== null) {
    return appMatch[1];
  }

  const modulesMatch = path.match(/\/modules\/(.+)\/resources\/js\/layouts\//);

  if (modulesMatch !== null) {
    return lastPathSegment(modulesMatch[1]);
  }

  const vendorMatch = path.match(/\/vendor\/([^/]+)\/([^/]+)\/resources\/js\/layouts\//);

  if (vendorMatch !== null) {
    return vendorMatch[2];
  }

  return null;
}

function layoutDiscoveryPriority(path: string): number {
  if (path.includes("/vendor/")) {
    return 0;
  }

  if (path.includes("/modules/")) {
    return 100;
  }

  if (path.includes("/app/")) {
    return 200;
  }

  return 300;
}

function normalizeDiscoveredLayoutModule(
  module: unknown,
): unknown {
  if (
    isObjectLike(module) &&
    "default" in module &&
    isObjectLike(module.default)
  ) {
    return module.default;
  }

  return module;
}

function lastPathSegment(path: string): string {
  const normalized = path.replaceAll("\\", "/").replace(/^\/+|\/+$/g, "");
  const parts = normalized.split("/");

  return parts[parts.length - 1] ?? normalized;
}
