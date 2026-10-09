<?php

declare(strict_types=1);

$root = dirname(__DIR__);

it('removes the entire demo/ directory', function () use ($root): void {
    expect(is_dir($root . '/demo'))->toBeFalse('demo/ directory should not exist');
});
