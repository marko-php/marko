# Marko Documentation Standards

Rules and conventions for generating and maintaining docs content. All contributors (human and AI) must follow these.

## Content Taxonomy

The docs site has five sections, each with a distinct purpose. Content belongs in exactly one section.

### Getting Started

**Purpose:** Onboard new developers from zero to productive.

**Audience:** Someone who has never used Marko before.

**Content style:** Linear, sequential. Each page builds on the previous. Assumes no prior Marko knowledge.

**Pages:** Introduction, Installation, Your First Application, Project Structure, Configuration.

**Rule:** This section is stable. Only update when the framework's onboarding flow changes.

### Concepts

**Purpose:** Explain *why* Marko works the way it does. Build mental models.

**Audience:** Someone who has completed Getting Started and wants to understand the architecture.

**Content style:** Explanation-oriented. Covers the design decisions, trade-offs, and patterns behind Marko's features. May reference multiple packages but doesn't teach you how to use them step-by-step.

**Examples:** "Modularity" explains the module system philosophy. "Dependency Injection" explains how DI works and why Marko chose constructor injection.

**Rule:** No `composer require` commands. No step-by-step instructions. Link to Packages for API details and Guides for practical usage.

### Packages (Reference)

**Purpose:** Complete reference for a single Composer package. The single source of truth for what a package does, how to configure it, and its full API.

**Audience:** A developer actively building with Marko who needs to look something up.

**Content style:** Reference-oriented. One page per package. Comprehensive but not tutorial-like. Assume the reader already understands the concepts.

**Each package page must include (in order):**

1. **Intro paragraph** -- One-liner from the package description, followed by a brief overview of what it provides. No `## Overview` heading (see Formatting Rules below).
2. **Installation** -- `composer require` command.
3. **Configuration** (if applicable) -- Config file examples with `title="config/filename.php"`.
4. **Usage** -- Key features with code examples.
5. **API Reference** (if applicable) -- Interfaces, key methods, return types.
6. **Related Packages** (if applicable) -- Links to driver/implementation packages.

**Rule:** Package pages are generated from their README.md files. The README is the upstream source. When a README is updated, the docs page should be updated to match.

### Guides (How-To)

**Purpose:** Teach how to accomplish a specific task that may span multiple packages.

**Audience:** A developer who knows the concepts and needs to wire things together for a real use case.

**Content style:** Task-oriented. Starts with a goal ("Cache data with pluggable backends"), shows the practical steps to achieve it, and explains the cross-cutting concerns (configuration, testing, customization). Shorter than a Tutorial -- focused on one topic, not a full project.

**How Guides differ from Packages:**
- A **Package** page documents `marko/cache` in isolation -- its API, config options, interfaces.
- A **Guide** page on "Caching" shows how to set up caching in your app, swap backends, use it with other packages, test with fakes, and handle common scenarios.

**How Guides differ from Tutorials:**
- A **Guide** solves one focused task: "How do I set up authentication?"
- A **Tutorial** builds a complete project from scratch across many tasks.

**Each guide page should include:**

1. **Intro paragraph** -- What this guide covers and when you'd need it.
2. **Setup** -- Packages to install, configuration needed.
3. **Core usage** -- The main task, step by step.
4. **Customization** -- How to extend or swap implementations.
5. **Testing** -- How to test this feature using `marko/testing` fakes.
6. **Related links** -- Links to relevant Package reference pages.

### Tutorials

**Purpose:** Guided, end-to-end project builds that tie many concepts and packages together.

**Audience:** Someone who wants to learn by building something complete.

**Content style:** Project-oriented. Starts from `composer create-project` and ends with a working application. Every step is explicit. The reader should be able to follow along and have a working result at the end.

**How Tutorials differ from Guides:**
- A **Tutorial** builds a complete, deployable project (blog, REST API, custom module).
- A **Guide** teaches one skill in isolation.

**Each tutorial page should include:**

1. **What You'll Build** -- Summary of the finished product.
2. **Prerequisites** -- PHP version, tools, packages.
3. **Numbered steps** -- `## Step 1: ...`, `## Step 2: ...`, etc.
4. **What You've Learned** -- Recap of concepts covered.
5. **Next Steps** -- Links to related guides and tutorials.

## Formatting Rules

### Headings

- **No `## Overview` heading.** The intro paragraph after frontmatter serves as the overview. Adding a `## Overview` heading creates a duplicate "Overview" entry in the right sidebar table of contents.
- All headings in page content render as uppercase via CSS. Write them in normal title case in Markdown (the CSS handles the visual transformation).
- Use h2 (`##`) for major sections, h3 (`###`) for subsections. Avoid h4+ when possible.

### Frontmatter

Every page must have:

```yaml
---
title: Page Title
description: One-line description used in search results and meta tags.
---
```

- For package pages, `title` is the Composer package name: `marko/database`
- For other pages, `title` is a short human-readable name: `Routing`, `Build a Blog`

### Intro Paragraph

Every page starts with a brief intro paragraph immediately after the frontmatter. This serves as the page overview. Do not wrap it in a heading.

For package pages, this is typically the package one-liner followed by a sentence expanding on what it provides.

### Code Blocks

- Add `title="filename"` to code blocks that represent file contents:
  ```php title="config/database.php"
  ```
- Omit titles for standalone snippets, CLI commands, or inline examples.
- Use the correct language identifier: `php`, `bash`, `json`, etc.

### Links

- Link to other docs pages using root-relative paths: `/docs/packages/core/`, `/docs/guides/routing/`
- Link to package pages from guides and concepts when referencing specific APIs.
- Link to guides from package pages when there's a richer usage walkthrough available.

### PHP Code Examples

- **Always use `use` statements at the top of code blocks.** Never use fully-qualified class names (FQCNs) inline. Write `use Marko\Core\Attributes\Preference;` at the top, then reference `Preference` in the code body.
- This applies to all PHP code blocks across all sections (Getting Started, Concepts, Packages, Guides, Tutorials).
- Short snippets showing a single method call may omit the `use` statement if the class is obvious from context, but full file examples and class definitions must always include them.
- **Constructor parameter names must match the class name (without `Interface` suffix), in camelCase.** For example: `ConnectionInterface $connection`, `CachePoolInterface $cachePool`, `EventDispatcherInterface $eventDispatcher`. Never use generic names like `$db`, `$cache`, or `$repo` — the parameter name should always be derivable from the type.

### CLI Commands

- **Use `marko` not `php marko` in all code examples.** The CLI is installed globally via `composer global require marko/cli`. The Installation page explains this setup and mentions `php marko` as a fallback — no other page should reference `php marko`.

### Punctuation

- Use em dashes (`---` renders as ---) for parenthetical statements, not hyphens.
- Use curly quotes only if the source README uses them; otherwise straight quotes are fine.

### Tables

- Use Markdown tables for structured reference data (CLI commands, type mappings, API methods).
- Keep tables scannable -- short cells, no paragraph-length content in cells.

## Sidebar Order

The sidebar is ordered by learning progression:

1. **Getting Started** -- First stop for new users
2. **Concepts** -- Understand the architecture
3. **Packages** -- Reference for building (most-used section for active developers)
4. **Guides** -- Cross-cutting how-to recipes
5. **Tutorials** -- End-to-end project builds

Packages comes before Guides because package reference pages contain the bulk of actionable documentation. Developers building with Marko will spend most of their time here.

## Migrating Package READMEs to Docs

When creating a docs page from a package README:

1. Create `docs/src/content/docs/packages/{package-name}.md`.
2. Follow the Package page structure above.
3. **Transfer all content from the README.** Do not drop, summarize, or omit any sections, callouts, tips, or notes. Every piece of information in the README must appear in the docs page. If it was worth writing, it's worth keeping.
4. Adapt formatting for Starlight (add `title=""` to code blocks, convert links to root-relative paths, use em dashes).
5. Apply docs conventions that may differ from the README (e.g., constructor parameter naming, cross-links to other package pages).
6. After the docs page is complete and verified, slim down the package's `README.md` to the format below.

## Package README Format

The docs site is the single source of truth for comprehensive documentation. Package READMEs should be slim and link to the docs. Do not duplicate content in both places.

**Structure (in order):**

1. **Title + one-liner** --- Package name as h1, followed by a one-sentence description.
2. **Installation** --- `composer require` command. Include a note if the package is typically installed via a metapackage or driver.
3. **Quick Example** --- One short code snippet showing the core idea. Keep it minimal --- just enough to convey what the package does.
4. **Documentation link** --- A link to the full docs page: `https://marko.build/docs/packages/{name}/`

**Exceptions:**

- Metapackages (like `marko/framework`) may include a table of bundled packages instead of a quick example.
- Driver packages may list available drivers before the documentation link.

**Example:**

```markdown
# marko/example

One-line description of what the package does.

## Installation

\`\`\`bash
composer require marko/example
\`\`\`

## Quick Example

\`\`\`php
// One short snippet showing the core idea
\`\`\`

## Documentation

Full usage, API reference, and examples: [marko/example](https://marko.build/docs/packages/example/)
```

## Content Principles

1. **No pseudo-documentation.** Every page must contain real, accurate information. Don't write placeholder content that sounds good but doesn't reflect the actual framework behavior.
2. **Docs site is the source of truth.** The docs site has the comprehensive documentation. Package READMEs are slim pointers to it. When adding or updating content, update the docs page --- not the README.
3. **Link, don't duplicate.** If a concept is explained in one place, link to it rather than re-explaining.
4. **Show, don't tell.** Code examples over prose descriptions.
5. **One source of truth per topic.** A topic lives in one section. Other sections link to it.
