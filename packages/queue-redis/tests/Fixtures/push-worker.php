<?php

declare(strict_types=1);

/*
 * Child process for the blocking pop test. Waits the given number of milliseconds, then pushes one job, so
 * the parent's blocking pop is already waiting when it arrives.
 *
 * Usage: php push-worker.php <autoload> <prefix> <delay-ms>
 */

use Marko\Queue\Redis\Tests\Fixtures\QueueHarness;
use Marko\Queue\Redis\Tests\Fixtures\TestJob;

[, $autoload, $prefix, $delayMs] = $argv;

require $autoload;

$queue = new QueueHarness(prefix: $prefix)->queue();

usleep((int) $delayMs * 1000);

$queue->push(new TestJob('pushed-while-waiting'));
