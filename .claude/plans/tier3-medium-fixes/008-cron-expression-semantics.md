# Task 008: Correct cron expression semantics

**Status**: pending
**Depends on**: [none]
**Retry count**: 0

## Description
`CronExpression` has multiple standard-cron semantics bugs: day-of-week `7` (Sunday) never matches (PHP `w` is 0-6); combined forms like `1-5,10` and stepped ranges like `0-58/2` mis-evaluate because lists/ranges go through `intval`; `*/n` only handles a leading `*` (no `a-b/n` and steps don't start from the range start); restricted day-of-month and day-of-week are ANDed when standard cron ORs them; and malformed expressions return false silently instead of failing loudly. Correct field parsing (ranges, lists, steps from range start), alias DOW 0/7, implement OR semantics for restricted DOM+DOW, and throw a loud error on malformed expressions.

## Context
- Related files: `packages/scheduler/src/CronExpression.php` (matches ~11-27, matchField ~29-60), scheduler package has no exceptions dir yet — add one (`MarkoException` subclass with static factory)
- Patterns to follow: parse each field into the set of matching integers (handle `*`, `a-b`, `a-b/n`, `*/n` stepping from the field's range start, `a,b,c`, and bare values); treat DOW `7` as `0`; for the day fields, when BOTH DOM and DOW are restricted (neither is `*`) match if EITHER matches (OR), otherwise the existing AND across fields holds; loud errors: throw a `MarkoException` subclass (message/context/suggestion) for a field count != 5 or an unparseable field instead of returning false.

## Requirements (Test Descriptions)
- [ ] `it matches Sunday when the day-of-week field is 7`
- [ ] `it matches Sunday when the day-of-week field is 0`
- [ ] `it matches a value present in a combined list-and-range field like 1-5,10`
- [ ] `it matches even values for a stepped range field like 0-58/2`
- [ ] `it steps a non-zero-based range from the range start so 10-20/5 matches 10, 15, 20 but not 12`
- [ ] `it steps a star-slash field from the start of the field range`
- [ ] `it matches when either a restricted day-of-month or a restricted day-of-week matches (OR semantics)`
- [ ] `it ANDs day-of-month and day-of-week when only one of them is restricted`
- [ ] `it matches Sunday from a day-of-week list containing the 7 alias like 0,7`
- [ ] `it throws a loud exception for an expression that does not have five fields`
- [ ] `it throws a loud exception for an unparseable field value`

## Acceptance Criteria
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
(Left blank - filled in by programmer during implementation)
