import * as fs from "node:fs";
import * as path from "node:path";
import type { Alias, PluginOption, UserConfig } from "vite";

type BaseConfigOptions = {
  entrypoints: Array<string | null | undefined>;
  plugins?: PluginOption[];
  config?: UserConfig;
};

export function createBaseConfig(options: BaseConfigOptions): UserConfig {
  const entrypoints = options.entrypoints.filter(
    (entrypoint): entrypoint is string =>
      typeof entrypoint === "string" && entrypoint !== "",
  );
  const projectRoot = process.cwd();

  const baseConfig: UserConfig = {
    base: resolveBasePath(),
    plugins: options.plugins ?? [],
    resolve: {
      alias: createMarkoAliases(projectRoot),
      preserveSymlinks: true,
    },
    server: {
      host: "0.0.0.0",
      port: 5173,
      strictPort: true,
      watch: {
        ignored: createIgnoredWatchPatterns(),
      },
    },
    build: {
      manifest: "manifest.json",
      outDir: "public/build",
      emptyOutDir: true,
      rollupOptions: {
        input: Array.from(new Set(entrypoints)),
      },
    },
  };

  return options.config ? mergeUserConfig(baseConfig, options.config) : baseConfig;
}

function resolveBasePath(): string {
  return isServeCommand() ? "/" : "/build/";
}

function isServeCommand(): boolean {
  return process.argv.includes("serve") || !process.argv.includes("build");
}

function createIgnoredWatchPatterns(): string[] {
  return [
    "**/.marko/**",
    "**/bootstrap/cache/**",
    "**/public/build/**",
    "**/public/hot",
    "**/public/storage/**",
    "**/storage/**",
  ];
}

function createMarkoAliases(projectRoot: string): Alias[] {
  const aliases: Alias[] = [
    {
      find: /^@\//,
      replacement: ensureTrailingSlash(
        normalizeForVitePath(path.resolve(projectRoot, "resources/js")),
      ),
    },
  ];

  for (const [moduleName, targetPath] of Object.entries(
    discoverModuleAliases(projectRoot),
  )) {
    aliases.push({
      find: new RegExp(`^@${escapeForRegExp(moduleName)}(?:(/.*))?$`),
      replacement: `${normalizeForVitePath(targetPath)}$1`,
    });
  }

  return aliases;
}

function discoverModuleAliases(projectRoot: string): Record<string, string> {
  const aliases: Record<string, string> = {};

  registerModulesDirectoryAliases(
    aliases,
    path.resolve(projectRoot, "modules"),
  );
  registerAppDirectoryAliases(
    aliases,
    path.resolve(projectRoot, "app"),
  );

  return aliases;
}

function registerModulesDirectoryAliases(
  aliases: Record<string, string>,
  modulesRoot: string,
): void {
  if (!fs.existsSync(modulesRoot)) {
    return;
  }

  registerNestedModuleAliases(aliases, modulesRoot);
}

function registerNestedModuleAliases(
  aliases: Record<string, string>,
  directory: string,
): void {
  const jsRoot = path.join(directory, "resources", "js");

  if (fs.existsSync(jsRoot)) {
    aliases[moduleAliasName(directory)] = jsRoot;

    return;
  }

  for (const entry of safeReadDir(directory)) {
    if (!entry.isDirectory()) {
      continue;
    }

    registerNestedModuleAliases(aliases, path.join(directory, entry.name));
  }
}

function registerAppDirectoryAliases(
  aliases: Record<string, string>,
  appRoot: string,
): void {
  if (!fs.existsSync(appRoot)) {
    return;
  }

  for (const moduleEntry of safeReadDir(appRoot)) {
    if (!moduleEntry.isDirectory()) {
      continue;
    }

    const jsRoot = path.join(
      appRoot,
      moduleEntry.name,
      "resources",
      "js",
    );

    if (fs.existsSync(jsRoot)) {
      aliases[moduleEntry.name] = jsRoot;
    }
  }
}

function moduleAliasName(directory: string): string {
  const normalizedDirectory = directory.replace(/\\/g, "/").replace(/\/+$/, "");
  const parts = normalizedDirectory.split("/");

  return parts[parts.length - 1] ?? normalizedDirectory;
}

function safeReadDir(directory: string): fs.Dirent[] {
  try {
    return fs.readdirSync(directory, { withFileTypes: true });
  } catch {
    return [];
  }
}

function ensureTrailingSlash(value: string): string {
  return value.endsWith("/") ? value : `${value}/`;
}

function normalizeForVitePath(value: string): string {
  return value.replace(/\\/g, "/");
}

function escapeForRegExp(value: string): string {
  return value.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
}

function mergeUserConfig(
  baseConfig: UserConfig,
  userConfig: UserConfig,
): UserConfig {
  return mergeValues(baseConfig, userConfig) as UserConfig;
}

function mergeValues(baseValue: unknown, overrideValue: unknown): unknown {
  if (overrideValue === undefined) {
    return baseValue;
  }

  if (Array.isArray(baseValue) && Array.isArray(overrideValue)) {
    return [...baseValue, ...overrideValue];
  }

  if (isPlainObject(baseValue) && isPlainObject(overrideValue)) {
    const merged: Record<string, unknown> = { ...baseValue };

    for (const [key, value] of Object.entries(overrideValue)) {
      merged[key] = key in merged
        ? mergeValues(merged[key], value)
        : value;
    }

    return merged;
  }

  return overrideValue;
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null && !Array.isArray(value);
}
