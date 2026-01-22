# Task 002: QueueException Hierarchy

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the queue exception hierarchy: QueueException (base), JobFailedException, SerializationException.

## Context
- QueueException extends MarkoException from marko/core
- JobFailedException for job execution failures with job context
- SerializationException for serialization/deserialization failures
- All exceptions should have static factory methods with context and suggestions

## Requirements (Test Descriptions)
- [ ] `QueueException has noDriverInstalled factory method`
- [ ] `QueueException has configFileNotFound factory method`
- [ ] `JobFailedException has fromException factory method`
- [ ] `JobFailedException includes job class name in message`
- [ ] `SerializationException has invalidJobData factory method`
- [ ] `SerializationException has unserializableClosure factory method`

## Implementation Notes
