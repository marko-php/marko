# marko-skills

Claude Code plugin providing scaffolding skills for Marko modules and plugins.

## Skills

- `create-module` — scaffold a new Marko module
- `create-plugin` — scaffold a new Marko Claude Code plugin

## Usage

Skills are invoked with the plugin namespace prefix:

```
/marko-skills:create-module
/marko-skills:create-plugin
```

## Install

Install via the marko marketplace:

```
/plugin install marko-skills@marko
```

## Skill content

Each skill's full description lives in `skills/<skill-name>/SKILL.md` once populated by the build process.
