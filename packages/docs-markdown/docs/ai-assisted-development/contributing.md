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

```markdown
# Skill: {Skill Name}

## When to use
One sentence describing when this skill applies.

## Steps
1. First step...
2. Second step...
3. ...

## Verification
How to confirm the task completed successfully.

## See also
- [Relevant docs page](https://marko.build/docs/...)
```

**Example** (`marko/payment/resources/ai/skills/add-gateway/SKILL.md`):

```markdown
# Skill: Add a Payment Gateway

## When to use
Use this skill when a developer asks to integrate a new payment provider.

## Steps
1. Create `app/{Module}/Gateway/{Provider}Gateway.php` implementing `GatewayInterface`
2. Implement `charge()`, `refund()`, and `tokenize()` methods
3. Register the gateway in `module.php` under `bindings`
4. Add configuration keys under `config/payment.php`
5. Dispatch `PaymentSucceeded` or `PaymentFailed` from `charge()`

## Verification
Ask your AI agent to call the `validate_module` MCP tool against `app/{Module}` — it should pass with no errors.

## See also
- [marko/payment README](https://github.com/markshust/marko/tree/develop/packages/payment)
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
