<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Repositories\PostRepository;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class PostController
{
    public function __construct(
        private readonly PostRepository $repository,
    ) {}

    #[Get('/blog')]
    public function index(): Response
    {
        $posts = $this->repository->findAll();
        $count = count($posts);

        return new Response("Blog Posts: $count found");
    }

    #[Get('/blog/{slug}')]
    public function show(
        string $slug,
    ): Response {
        $post = $this->repository->findBySlug($slug);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        return new Response("Post: $post->title");
    }
}
