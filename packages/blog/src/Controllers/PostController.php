<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class PostController
{
    #[Get('/blog')]
    public function index(): Response
    {
        return new Response('Blog Index: Route matched successfully');
    }

    #[Get('/blog/{slug}')]
    public function show(
        string $slug,
    ): Response {
        return new Response("Blog Post: $slug");
    }
}
