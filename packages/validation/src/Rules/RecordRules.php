<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Validation\Contracts\RecordLookupInterface;

/**
 * Builds the rules that look up stored records. Inject it where you build rules:
 *
 *     'channel_id' => ['required', $this->recordRules->exists(Channel::class)],
 *     'email' => ['required', 'email', $this->recordRules->unique(User::class, 'email')->ignore($user->id)],
 *
 * Resolving it needs a RecordLookupInterface driver, such as marko/validation-database.
 */
readonly class RecordRules
{
    public function __construct(
        private RecordLookupInterface $lookup,
    ) {}

    /**
     * The value must match $property of a stored $entityClass record.
     *
     * @param class-string $entityClass
     */
    public function exists(
        string $entityClass,
        string $property = 'id',
    ): Exists {
        return new Exists($this->lookup, $entityClass, $property);
    }

    /**
     * No stored $entityClass record may already have the value in $property.
     *
     * @param class-string $entityClass
     */
    public function unique(
        string $entityClass,
        string $property,
    ): Unique {
        return new Unique($this->lookup, $entityClass, $property);
    }
}
