# Task 002: ExtensionMetadata + EntityExtensionMetadataFactory

**Status**: completed
**Depends on**: [001]
**Retry count**: 0

## Description
Create `ExtensionMetadata` — a value object that holds parsed column and property metadata for a single extension class — and `EntityExtensionMetadataFactory` that parses an `EntityExtension` subclass via reflection, extracting `#[Column]`-annotated properties. This is similar to `EntityMetadataFactory` but without requiring `#[Table]` or a primary key.

## Context
- Related files:
  - `packages/database/src/Entity/EntityMetadata.php` — reference shape
  - `packages/database/src/Entity/EntityMetadataFactory.php` — reference for property parsing logic
  - `packages/database/src/Entity/PropertyMetadata.php`
  - `packages/database/src/Entity/ColumnMetadata.php`
  - `packages/database/src/Attributes/Column.php`
- `ExtensionMetadata` holds: `extensionClass`, `entityClass`, `properties: array<string, PropertyMetadata>`, `columns: array<ColumnMetadata>`. It MUST be declared `readonly` with constructor property promotion (parallel to `EntityMetadata`).
- The factory must reuse the same PHP→DB type inference and camelCase→snake_case logic as `EntityMetadataFactory`; do not duplicate with different behaviour, but duplication is acceptable since the classes have different validation rules
- BackedEnum default values must be converted to their backing value, matching `EntityMetadataFactory` behaviour
- JSON column type/nullable mismatch validation (parallel to `EntityMetadataFactory`) must be enforced
- Extension classes must have at least one `#[Column]` property — throw `EntityException` if none found (new factory method on `EntityException`: `extensionHasNoColumns`)
- Extension classes must not declare a primary key column — throw `EntityException` if one is found (new factory method: `extensionDeclaresPrimaryKey`)
- Extension classes must not declare relationships (`#[HasOne]`, `#[HasMany]`, `#[BelongsTo]`, `#[BelongsToMany]`) — these are out of scope; throw `EntityException` if any found (new factory method: `extensionDeclaresRelationship`)
- Extension classes must not declare `#[Table]` or `#[Index]` attributes — throw `EntityException` if any found
- The factory reads the `#[ExtensionOf]` attribute on the extension class to populate `entityClass`; if missing, throw `EntityException` (new factory method: `extensionMissingExtensionOf`)

## Requirements (Test Descriptions)
- [ ] `it parses public Column-annotated properties into ExtensionMetadata`
- [ ] `it stores the extension class and entity class on ExtensionMetadata`
- [ ] `it throws when extension class has no Column-annotated properties`
- [ ] `it throws when extension class declares a primary key column`
- [ ] `it throws when extension class declares a relationship attribute`
- [ ] `it throws when extension class is missing the ExtensionOf attribute`
- [ ] `it infers database types from PHP scalar types`
- [ ] `it converts camelCase property names to snake_case column names`
- [ ] `it uses explicit column name from Column attribute when provided`
- [ ] `it correctly marks nullable properties`
- [ ] `it captures declared default values`
- [ ] `it converts BackedEnum default values to their backing value`
- [ ] `it throws when extension class does not extend EntityExtension`
- [ ] `it throws when a json column type does not match the PHP array type`
- [ ] `it caches parsed metadata by class`

## Acceptance Criteria
- All requirements have passing tests
- `ExtensionMetadata` lives at `Marko\Database\Entity\ExtensionMetadata`
- `EntityExtensionMetadataFactory` lives at `Marko\Database\Entity\EntityExtensionMetadataFactory`
- New static factory methods added to `Marko\Database\Exceptions\EntityException`:
  - `extensionHasNoColumns(string $extensionClass)`
  - `extensionDeclaresPrimaryKey(string $extensionClass, string $propertyName)`
  - `extensionDeclaresRelationship(string $extensionClass, string $propertyName)`
  - `extensionDeclaresTableAttribute(string $extensionClass)` (only if `#[Table]` is found on an extension)
  - `extensionMissingExtensionOf(string $extensionClass)`
  - `notExtendsEntityExtension(string $extensionClass)`
- Code follows project standards
