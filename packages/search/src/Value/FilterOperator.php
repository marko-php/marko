<?php

declare(strict_types=1);

namespace Marko\Search\Value;

enum FilterOperator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
    case In = 'in';
    case Like = 'like';
}
