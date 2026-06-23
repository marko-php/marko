<?php

declare(strict_types=1);

namespace Marko\DevAi\Writing;

enum WriteOutcome
{
    case Created;
    case Updated;
    case SkippedNoMarkers;
}
