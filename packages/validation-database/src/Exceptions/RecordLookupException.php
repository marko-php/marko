<?php

declare(strict_types=1);

namespace Marko\Validation\Database\Exceptions;

use Marko\Core\Exceptions\MarkoException;

/**
 * A RecordRules lookup that names something its entity cannot answer: a missing class, a property that is not a
 * column, or a comparison the column cannot take.
 */
class RecordLookupException extends MarkoException
{
    public static function unknownEntity(
        string $entityClass,
    ): self {
        return new self(
            message: "Cannot look up records of '$entityClass': no such class",
            context: 'Building an Exists or Unique rule through RecordRules',
            suggestion: 'Pass the entity class with ::class, e.g. $recordRules->exists(Channel::class), and '
                . 'check its namespace and import',
        );
    }

    /**
     * @param list<string> $properties The entity's column properties
     */
    public static function unknownProperty(
        string $entityClass,
        string $property,
        array $properties,
    ): self {
        return new self(
            message: "Entity '$entityClass' has no column property '$property'",
            context: 'Building an Exists or Unique rule through RecordRules; rules name entity properties, '
                . 'not table columns',
            suggestion: 'Use one of its #[Column] properties: ' . implode(', ', $properties),
        );
    }

    public static function encryptedProperty(
        string $entityClass,
        string $property,
    ): self {
        return new self(
            message: "Cannot look up records by encrypted property '$property' on entity '$entityClass'",
            context: 'Building an Exists or Unique rule through RecordRules',
            suggestion: 'Encrypted values are non-deterministic and cannot be matched in SQL. Look the record up '
                . 'by another property, e.g. a hash column',
        );
    }

    public static function caseInsensitiveNeedsText(
        string $entityClass,
        string $property,
        string $type,
    ): self {
        return new self(
            message: "Cannot compare '$property' on entity '$entityClass' case-insensitively: it is $type, not string",
            context: 'Running a Unique rule built with caseInsensitive()',
            suggestion: 'Use caseInsensitive() only on a string property, such as an email or a slug',
        );
    }
}
