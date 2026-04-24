# Task 043: Implement query-time embedding via transformers-php

**Status**: pending
**Depends on**: 041
**Retry count**: 0

## Description
Implement `QueryEmbedder` that takes a user query string and returns a 384-dim float vector suitable for sqlite-vec similarity search. Uses the same bundled bge-small-en-v1.5 model as the index builder.

## Context
- Namespace: `Marko\DocsVec\Query\QueryEmbedder`
- Uses `VecRuntime` for model access
- Caches model loading so repeated queries are fast

## Requirements (Test Descriptions)
- [ ] `it embeds a short query string into a 384-dimensional float vector`
- [ ] `it produces stable embeddings for the same input`
- [ ] `it handles empty strings with a loud error`
- [ ] `it reuses the loaded model across multiple embed calls`
- [ ] `it normalizes the output vector to unit length`

## Acceptance Criteria
- Inference time under 200ms for typical queries on modern hardware
- No model reloading between queries

## Implementation Notes
(Filled in by programmer during implementation)
