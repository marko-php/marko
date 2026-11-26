<?php

declare(strict_types=1);

namespace Marko\Database\Migration;

/**
 * What a failed migration left behind in the database.
 */
enum MigrationFailureOutcome
{
    case RolledBack;
    case MayBePartlyApplied;
    case Unknown;
}
