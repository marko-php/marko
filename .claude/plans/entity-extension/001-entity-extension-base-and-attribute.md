# Task 001: EntityExtension base class + `#[ExtensionOf]` attribute

**Status**: completed
**Depends on**: none
**Retry count**: 0

## Description
Create the two foundational primitives for the entity extension system: the `EntityExtension` abstract base class (parallel to `Entity`) and the `#[ExtensionOf]` attribute that declares which entity class an extension targets. These have no logic — they're the type anchors everything else builds on.

## Context
- Related files: `packages/database/src/Entity/Entity.php`, `packages/database/src/Attributes/Column.php`
- Both live in `packages/database` — no new package needed
- `ExtensionOf` cannot be a class name (`extends` is a reserved PHP keyword, class names are case-insensitive so `Extends` is also forbidden); use `ExtensionOf`
- The attribute target is `TARGET_CLASS` — it goes on the extension class, not on properties
- The attribute MUST be declared as `readonly` and constructed with constructor property promotion, mirroring `packages/database/src/Attributes/Table.php`
- The attribute's stored class is exposed as a public `entityClass` property (typed `string`, but documented as `class-string<Entity>` via phpdoc) for consumers to read
- Validation that the argument is a valid `Entity` subclass happens in discovery (task 003), not in the attribute constructor
- `EntityExtension` is `abstract` and has no body — pure type anchor (parallel to `Entity`)

## Requirements (Test Descriptions)
- [ ] `it can be extended to create a concrete extension class`
- [ ] `it rejects direct instantiation as an abstract class`
- [ ] `it has an ExtensionOf attribute that accepts an entity class string`
- [ ] `it targets class-level application only`
- [ ] `it stores the entity class in the attribute`

## Acceptance Criteria
- All requirements have passing tests
- `EntityExtension` lives at `Marko\Database\Entity\EntityExtension`
- `ExtensionOf` lives at `Marko\Database\Attributes\ExtensionOf`
- Code follows project standards
