<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Repositories\PostRepository;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class PostController
{
    public function __construct(
        private readonly PostRepository $repository,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/blog')]
    public function index(): Response
    {
        $posts = $this->repository->findAll();

        return $this->view->render('blog::post/index', [
            'posts' => $posts,
        ]);
    }

    #[Get('/blog/{slug}')]
    public function show(
        string $slug,
    ): Response {
        $post = $this->repository->findBySlug($slug);

        if ($post === null) {
            return new Response('Post not found', 404);
        }

        return $this->view->render('blog::post/show', [
            'post' => $post,
        ]);
    }
}
