---
title: JetBrains Junie
description: Set up Marko's AI tooling with JetBrains Junie — guidelines and skill distribution inside PhpStorm and IntelliJ.
---

[JetBrains Junie](https://www.jetbrains.com/junie/) is JetBrains' agentic AI assistant built into PhpStorm, IntelliJ IDEA, and other JetBrains IDEs. `devai:install` configures it with a project guidelines file and Marko skills.

## Prerequisites

- PhpStorm 2024.1+ or IntelliJ IDEA 2024.1+ with Junie enabled
- Junie plugin activated in your IDE
- `marko/devai` installed (see [Installation](../installation/))

## What devai:install writes

Running `marko devai:install` with Junie detected produces the following files:

```
junie/guidelines.md                # Project guidelines read by Junie on each session
junie/skills/                      # Marko skill files distributed for Junie
AGENTS.md                          # Shared guidelines file (written if not already present)
```

Junie does not implement MCP registration or LSP registration.

### guidelines.md

The `junie/guidelines.md` file (under a `junie/` directory in the project root, not `.junie/`) is Junie's primary source of project context. The installer writes merged Marko guidelines:

- Module structure and naming conventions
- Available tools and their descriptions
- Project-specific guidelines from every installed package's `resources/ai/guidelines.md`

### Skills

Marko skill bundles are written to `junie/skills/` so Junie can reference them during agentic tasks.

### AGENTS.md

If no `AGENTS.md` exists in the project root, `devai:install` creates one with the same guidelines content. If `AGENTS.md` already exists, it is left untouched.

## Manual verification

1. Open your project in PhpStorm with Junie enabled.
2. Open the Junie panel and ask: `What conventions should I follow for this project?` — Junie should describe the Marko guidelines from `junie/guidelines.md`.
3. Verify `junie/guidelines.md` exists in the project root.
4. Verify `junie/skills/` contains skill files.

## Agent-specific tips

- **Agentic tasks**: Junie excels at longer, multi-step tasks. Keep `junie/guidelines.md` focused on conventions and put step-by-step skill instructions in `junie/skills/` so they are only loaded when relevant.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai)
