<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\View\Exceptions\ViewException;

it('ViewException extends MarkoException', function () {
    $exception = new ViewException('Test error');

    expect($exception)->toBeInstanceOf(MarkoException::class);
});
