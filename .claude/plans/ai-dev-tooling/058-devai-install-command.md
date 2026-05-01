# Task 058: Implement devai:install interactive command

**Status**: pending
**Depends on**: 048, 049, 050, 051, 052, 053, 054, 055, 056, 057
**Retry count**: 0

## Description
Implement the `marko devai:install` interactive console command. Shows an agent picker (checkbox list of all detected agents), docs driver picker (docs-vec default, docs-fts alternative), then invokes every selected adapter to write its config files, register MCP/LSP, and distribute skills.

## Context
- Namespace: `Marko\DevAi\Commands\InstallCommand`
- Attribute: `#[Command(name: 'devai:install')]`
- Uses interactive prompts (reuses Marko's existing CLI prompt utilities if present, otherwise implements minimal)
- Prints summary of files written and registrations made
- Non-interactive mode: `--agents=claude-code,codex --docs-driver=vec`

## Requirements (Test Descriptions)
- [ ] `it is registered via Command attribute with name devai:install`
- [ ] `it detects installed agents and presents them as a checkbox picker`
- [ ] `it prompts for docs driver choice with vec as default`
- [ ] `it invokes each selected adapter writeGuidelines registerMcp registerLsp distributeSkills`
- [ ] `it supports non-interactive mode via flags`
- [ ] `it prints a summary of changes made`
- [ ] `it detects a prior install by reading .marko/devai.json and early-exits with a helpful message pointing the user to devai:update`
- [ ] `it supports a --force flag to re-run from scratch (overwrites all generated files)`
- [ ] `it writes .marko/devai.json on successful install capturing selected agents and docs driver choice`
- [ ] `it writes or updates .gitignore entries for generated files if user opts in`

## Acceptance Criteria
- Interactive and non-interactive paths both tested
- Summary output clearly lists each agent's outputs

## Implementation Notes
(Filled in by programmer during implementation)
