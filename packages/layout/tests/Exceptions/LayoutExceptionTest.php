<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Layout\Exceptions\LayoutException;

it('has a LayoutException base class extending MarkoException', function (): void {
    $exception = new LayoutException(message: 'test');

    expect($exception)->toBeInstanceOf(MarkoException::class);
});
