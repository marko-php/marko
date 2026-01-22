# Task 006: ModuleTemplateResolver

**Status**: completed
**Depends on**: 004
**Retry count**: 0

## Description
Create the ModuleTemplateResolver that discovers templates in modules.

## Context
- Parses module::path template syntax
- Searches app > modules > vendor directories
- Uses ModuleRepository to get module paths
- Throws TemplateNotFoundException with searched paths

## Requirements (Test Descriptions)
- [x] `ModuleTemplateResolver implements TemplateResolverInterface`
- [x] `ModuleTemplateResolver parses module::path syntax`
- [x] `ModuleTemplateResolver searches in module priority order`
- [x] `ModuleTemplateResolver returns first match found`
- [x] `ModuleTemplateResolver throws TemplateNotFoundException when not found`
- [x] `ModuleTemplateResolver getSearchedPaths returns all paths checked`

## Implementation Notes
