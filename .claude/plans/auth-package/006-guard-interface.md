# Task 006: GuardInterface

**Status**: pending
**Depends on**: 003
**Retry count**: 0

## Description
Create the GuardInterface contract for authentication strategies (session, token, etc.).

## Context
- Related files: See interface design in _plan.md
- Guards handle the actual authentication logic
- Different guards for different auth strategies

## Requirements (Test Descriptions)
- [ ] `it creates GuardInterface with check method returning bool`
- [ ] `it creates GuardInterface with guest method returning bool`
- [ ] `it creates GuardInterface with user method returning nullable AuthenticatableInterface`
- [ ] `it creates GuardInterface with id method returning nullable identifier`
- [ ] `it creates GuardInterface with attempt method`
- [ ] `it creates GuardInterface with login method`
- [ ] `it creates GuardInterface with loginById method`
- [ ] `it creates GuardInterface with logout method`
- [ ] `it creates GuardInterface with setProvider method`
- [ ] `it creates GuardInterface with getName method`

## Acceptance Criteria
- All requirements have passing tests
- Interface supports both session and stateless authentication
- Methods are properly typed

## Implementation Notes
(Left blank - filled in by programmer during implementation)
