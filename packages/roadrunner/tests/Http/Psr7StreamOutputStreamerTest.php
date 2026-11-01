<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Http;

use Marko\Roadrunner\Http\Psr7StreamOutputStreamer;
use Nyholm\Psr7\Stream;

it('appends every chunk to the psr7 stream', function (): void {
    $stream = Stream::create();
    $outputStreamer = new Psr7StreamOutputStreamer($stream);

    $first = $outputStreamer->write('hello ');
    $second = $outputStreamer->write('world');

    expect($first)->toBeTrue()
        ->and($second)->toBeTrue()
        ->and((string) $stream)->toBe('hello world');
});

it('never reports the connection as aborted', function (): void {
    $outputStreamer = new Psr7StreamOutputStreamer(Stream::create());

    expect($outputStreamer->connectionAborted())->toBeFalse();
});

it('leaves output buffers in place when prepared', function (): void {
    $outputStreamer = new Psr7StreamOutputStreamer(Stream::create());
    ob_start();
    $level = ob_get_level();

    try {
        $outputStreamer->prepare();
        $levelAfterPrepare = ob_get_level();
    } finally {
        ob_end_clean();
    }

    expect($levelAfterPrepare)->toBe($level);
});
