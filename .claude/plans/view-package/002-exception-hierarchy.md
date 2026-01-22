# Task 002: ViewException Hierarchy

**Status**: completed
**Depends on**: 001
**Retry count**: 0

## Description
Create the exception hierarchy for the view package.

## Context
- ViewException - base exception for all view errors
- TemplateNotFoundException - when template file cannot be found
- NoDriverException - when no view driver is installed

## Requirements (Test Descriptions)
- [x] `ViewException extends MarkoException`
- [x] `TemplateNotFoundException has searched paths context`
- [x] `NoDriverException has suggestion for installing driver`

## Implementation Notes
