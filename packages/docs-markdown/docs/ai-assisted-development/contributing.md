---
title: Contributing Guidelines and Skills
description: How third-party package authors can ship AI guidelines and agent skills using the resources/ai/ convention.
---

Every Marko package can ship its own AI guidelines and agent skills. When a project runs `marko devai:install`, the installer discovers and merges these files from every installed package automatically — no registration required.

## The resources/ai/ convention

Place AI-related assets at the following paths inside your package:

```
resources/
  ai/
    guidelines.md          # Always-on project guidelines for this package
    skills/
      {skill-name}/
        SKILL.md           # Step-by-step instructions for a specific task
```

Both files are optional. Ship whichever ones are relevant to your package.

## guidelines.md

`resources/ai/guidelines.md` contains conventions, patterns, and constraints that should always be in the agent's context when working in a project that has your package installed.

Keep it short and focused — this file is injected into every session regardless of what the developer is doing.

**Good candidates for guidelines.md:**
- Naming conventions your package enforces
- Which classes or methods to extend vs. override
- Common mistakes to avoid
- Links to relevant package documentation

**Example** (`marko/payment/resources/ai/guidelines.md`):

```markdown
## marko/payment

- Payment gateways implement `Marko\Payment\Gateway\GatewayInterface`
- Never store raw card data; always tokenize via `$gateway->tokenize()`
- Use `PaymentFailed` and `PaymentSucceeded` events, never throw from observers
- See [marko/payment docs](https://marko.build/docs/packages/payment/)
```

## skills/

Each skill lives in its own directory under `resources/ai/skills/{skill-name}/` and contains a single `SKILL.md` file.

A skill is a **step-by-step workflow** the agent can follow for a specific, bounded task. Unlike guidelines, skills are loaded on demand — the agent requests a skill by name when the developer asks for something task-specific.

### SKILL.md format

Every `SKILL.md` **must start with YAML frontmatter** containing at least `name` and `description`. The `description` is what the agent matches against to decide whether to load the skill, so it must name the trigger conditions concretely. Skills that ship without frontmatter are skipped by `marko/devai` with a warning — they cannot be auto-discovered.

**Naming convention.** The skill directory name, the `name` field, and any references in docs must match. Prefix the name with your package's namespace to avoid clobbering same-named skills from other sources (e.g. `marko-create-module`, not `create-module`).

```markdown
---
name: vendor-skill-name
description: One or two sentences naming exactly when this skill should be invoked. Use whenever the user asks to {trigger 1}, {trigger 2}, or {trigger 3}.
---

# {Skill Title}

Short paragraph explaining what the skill does and what it produces.

## When to use

Restate the trigger conditions in your own words. Keep this short — the agent already has the description.

## Step 1 — {first step name}

Imperative instructions. Show real code examples grounded in the package's actual conventions.

## Step 2 — {second step name}

...

## Verification

How to confirm the task completed successfully (e.g. "ask the agent to call `validate_module` against the new module").

## See also

- [Relevant docs page](https://marko.build/docs/...)
```

**Example** (`acme/payment/resources/ai/skills/acme-add-payment-gateway/SKILL.md`):

```markdown
---
name: acme-add-payment-gateway
description: Scaffold a new payment gateway implementation against the marko/payment GatewayInterface. Use whenever the user asks to add a payment provider, integrate Stripe/Braintree/etc., or implement charge/refund/tokenize for a new gateway.
---

# Add a payment gateway

Create a new gateway class implementing `GatewayInterface`, register it in
`module.php`, and wire its config keys.

## Step 1 — Create the gateway class

Create `app/{Module}/Gateway/{Provider}Gateway.php` implementing
`Acme\Payment\Contracts\GatewayInterface`. Implement `charge()`, `refund()`,
and `tokenize()`.

## Step 2 — Register the binding

Add to `module.php` under `bindings`:

```php
GatewayInterface::class => fn ($c) => new {Provider}Gateway(/* … */),
```

## Step 3 — Add config keys

...

## Verification

Ask the agent to call `validate_module` against `app/{Module}` — it should
pass with no errors.

## See also

- [`acme/payment` README](https://github.com/acme/payment)
```

## How devai:install discovers your assets

The installer runs the following logic for every package in `vendor/`:

1. Checks for `vendor/{vendor}/{package}/resources/ai/guidelines.md`
2. If found, appends the content (with a heading) to the active agent's guidelines file
3. Scans `vendor/{vendor}/{package}/resources/ai/skills/` for subdirectories
4. Registers each skill by name so agents can load them on demand

No additional configuration is needed in your `composer.json` or `module.php`. The path convention is the only contract.

## Testing your assets

To verify your `resources/ai/` files are picked up correctly:

1. Install your package in a test Marko project
2. Run `marko devai:install`
3. Inspect the generated agent guidelines file (e.g., `AGENTS.md`) — your package should have its own subsection under `## Package Guidelines`
4. Confirm your skills appear under the chosen agent's skills directory (e.g., `.claude/skills/`, `.agents/skills/`, `.gemini/skills/`, `junie/skills/`)

## Best practices

- **Keep guidelines concise** — Agents have finite context windows. Every line you add to `guidelines.md` competes with the developer's own code.
- **One skill per task** — Skills work best when they are tightly scoped. "Add a payment gateway" is a good skill; "build a full e-commerce module" is not.
- **Link to docs** — Include a link back to your package's documentation page in every skill. Agents follow links when they need more detail.
- **Use imperative language** — Write steps as commands ("Create a class", "Implement the interface"), not descriptions ("A class should be created").
- **Test with at least one agent** — Run through the [Verification checklist](./verification-checklist/) with your package installed to confirm the integration works end-to-end.

## Package READMEs

- [`marko/devai`](https://github.com/markshust/marko/tree/develop/packages/devai) — installer that discovers resources/ai/ files
