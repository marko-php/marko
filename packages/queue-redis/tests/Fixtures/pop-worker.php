<?php

declare(strict_types=1);

/*
 * Child process for the concurrent pop test. Opens its own Redis client, waits until a shared start time so
 * every worker pops at once, then pops until the queue is empty and prints the ids it got, one per line.
 *
 * Usage: php pop-worker.php <autoload> <prefix> <start-microtime>
 */

use Marko\Queue\Redis\Tests\Fixtures\QueueHarness;

[, $autoload, $prefix, $startAt] = $argv;

require $autoload;

$queue = new QueueHarness(prefix: $prefix)->queue();

// Connect before the start time, so the race is between the pops.
$queue->size();

while (microtime(true) < (float) $startAt) {
    usleep(100);
}

while (($job = $queue->pop()) !== null) {
    echo $job->id, "\n";
}
