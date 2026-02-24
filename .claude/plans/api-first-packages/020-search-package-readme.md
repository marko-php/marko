# Task 020: Search Package README

**Status**: pending
**Depends on**: 019
**Retry count**: 0

## Description
Create the README.md for the marko/search package following the project's Package README Standards.

## Context
- Package: `packages/search/`
- Follow README format from `.claude/code-standards.md` "Package README Standards" section
- Show making entities searchable, performing searches with criteria, and filtering
- Study existing READMEs for tone and format

## Requirements (Test Descriptions)
- [ ] `README.md exists with title, overview, installation, usage, and API reference sections`
- [ ] `README.md shows SearchableInterface implementation on an entity`
- [ ] `README.md shows searching with criteria, filters, and pagination`
- [ ] `README.md documents available filter operators`

## Acceptance Criteria
- README.md follows Package README Standards exactly
- Code examples use multiline parameter signatures per code standards
- Notes about future driver support (Elasticsearch, Meilisearch)
- API Reference lists SearchInterface, SearchCriteria, SearchResult

## Implementation Notes
