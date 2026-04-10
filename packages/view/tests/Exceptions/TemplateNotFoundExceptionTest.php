<?php

declare(strict_types=1);

use Marko\View\Exceptions\TemplateNotFoundException;
use Marko\View\Exceptions\ViewException;

it('TemplateNotFoundException has searched paths context', function () {
    $searchedPaths = [
        '/app/blog/views/posts/show.latte',
        '/modules/blog/views/posts/show.latte',
        '/vendor/acme/blog/views/posts/show.latte',
    ];

    $exception = TemplateNotFoundException::forTemplate('posts/show', $searchedPaths);

    expect($exception)->toBeInstanceOf(ViewException::class)
        ->and($exception->getMessage())->toContain('posts/show')
        ->and($exception->getContext())->toContain('/app/blog/views/posts/show.latte')
        ->and($exception->getContext())->toContain('/modules/blog/views/posts/show.latte')
        ->and($exception->getContext())->toContain('/vendor/acme/blog/views/posts/show.latte');
});
