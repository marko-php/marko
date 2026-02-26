<?php

declare(strict_types=1);

namespace Marko\PubSub;

use Generator;
use IteratorAggregate;

/**
 * @extends IteratorAggregate<int, Message>
 */
interface Subscription extends IteratorAggregate
{
    /**
     * @return Generator<int, Message>
     */
    public function getIterator(): Generator;

    /**
     * Cancel the subscription.
     */
    public function cancel(): void;
}
