# Task 027: Implement get_config_schema, check_config_key MCP tools

**Status**: pending
**Depends on**: 022
**Retry count**: 0

## Description
Implement `get_config_schema` (returns the full dot-notation config key tree known to the index) and `check_config_key` (validates whether a given dot-notation key exists and returns its declared default + source location).

## Context
- Namespace: `Marko\Mcp\Tools\GetConfigSchemaTool`, `CheckConfigKeyTool`
- `check_config_key` input: `{ key: string }` — returns `{ exists: bool, default: mixed, file: string, line: int }`

## Requirements (Test Descriptions)
- [ ] `it registers get_config_schema returning all indexed ConfigKeyEntry records`
- [ ] `it registers check_config_key`
- [ ] `it returns exists true with metadata for a valid key`
- [ ] `it returns exists false for unknown keys with closest-match suggestions`
- [ ] `it includes source file and line for known keys`

## Acceptance Criteria
- Suggestions use Levenshtein distance or similar for typo correction
- Results match what ConfigScanner produced

## Implementation Notes
(Filled in by programmer during implementation)
