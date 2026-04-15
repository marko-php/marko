# Task 011: Nested Slot Rendering (Multi-Pass)

**Status**: complete
**Depends on**: 010
**Retry count**: 0

## Description
Extend `LayoutProcessor` to support nested slots via dot-notation. Components can define sub-slots (e.g., `slots: ['tab.details', 'tab.reviews']`), and other components can target those sub-slots (e.g., `slot: 'tab.reviews'`). This requires multi-pass rendering: parent components render first to create their sub-slot placeholders, then child components fill those sub-slots.

## Context
- Related files: Task 010's `LayoutProcessor`
- Dot-notation: `tab.reviews` means "render inside the component that defines `tab.reviews` in its `slots` array"
- Parent component template includes `{slot tab.details}{/slot}` and `{slot tab.reviews}{/slot}` placeholders
- Rendering order: layout slots first (top-level), then sub-slots are filled in subsequent passes
- Circular slot references must be detected and rejected with a loud error
- A component's sub-slots are scoped — only components explicitly targeting `tab.reviews` render there

## Requirements (Test Descriptions)
- [x] `it renders components into parent component sub-slots`
- [x] `it renders parent component before filling its sub-slots`
- [x] `it supports multiple levels of nesting`
- [x] `it throws SlotNotFoundException when targeting non-existent sub-slot`
- [x] `it detects circular slot references and throws an error`
- [x] `it renders sub-slot components in sortOrder`
- [x] `it handles mix of top-level and nested slot components on same page`

## Acceptance Criteria
- All requirements have passing tests
- Multi-pass rendering is efficient (minimal re-renders)
- Circular references detected at collection time, not render time
- No decrease in test coverage

## Implementation Notes
- Extended `LayoutProcessor` with a recursive `renderSlot()` method that renders parent components first, then fills sub-slot placeholders (`{slot name}{/slot}`) in the rendered HTML
- Built a full slot graph at validation time: layout slots + all component-defined sub-slots
- Circular reference detection uses DFS traversal over the slot graph before any rendering occurs
- `SlotNotFoundException` is thrown for any component targeting a slot not in the graph (layout slots or component sub-slots)
- Tests live at `packages/layout/tests/Unit/LayoutProcessorNestedTest.php`
