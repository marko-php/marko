# Task 010: Event System (Observer, Dispatcher)

**Status**: pending
**Depends on**: 002, 005
**Retry count**: 0

## Description
Create the event system with `#[Observer]` attribute, EventDispatcher, and observer discovery. Events decouple "something happened" from "react to it", allowing multiple independent reactions to the same event.

## Context
- Location: `packages/core/src/Attributes/` and `packages/core/src/Event/`
- Events can be class-based (type-safe) or string-based (simple)
- Observers are discovered via #[Observer] attribute
- Priority determines execution order (higher runs first)

## Requirements (Test Descriptions)
- [ ] `it creates Observer attribute with event and optional priority parameters`
- [ ] `it defaults observer priority to 0 when not specified`
- [ ] `it creates Event base class for type-safe events`
- [ ] `it discovers observer classes in module src directories`
- [ ] `it extracts event class/name from Observer attribute`
- [ ] `it throws exception when observer missing handle method`
- [ ] `it dispatches event to all registered observers`
- [ ] `it executes observers in priority order (higher first)`
- [ ] `it passes event object to observer handle method`
- [ ] `it injects observer dependencies via container`
- [ ] `it allows event to carry data accessible by observers`
- [ ] `it supports stopping event propagation from observer`

## Acceptance Criteria
- All requirements have passing tests
- Event dispatch is synchronous (async is future enhancement)
- Observer discovery handles nested namespaces
- Code follows strict types declaration

## Files to Create
```
packages/core/src/Attributes/
  Observer.php              # #[Observer(event: UserCreated::class, priority: 100)]
packages/core/src/Event/
  Event.php                 # Base class for events
  EventDispatcher.php       # Dispatches events to observers
  EventDispatcherInterface.php
  ObserverDefinition.php    # Value object for discovered observer
  ObserverDiscovery.php     # Finds observer classes in modules
  ObserverRegistry.php      # Stores observers indexed by event
```

## Implementation Notes
(Left blank - filled in by programmer during implementation)
