<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Controllers;

use DateTimeImmutable;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Author;
use Marko\Blog\Events\Author\AuthorCreated;
use Marko\Blog\Events\Author\AuthorDeleted;
use Marko\Blog\Events\Author\AuthorUpdated;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post as PostRoute;
use Marko\Routing\Attributes\Put;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

#[Middleware(AdminAuthMiddleware::class)]
class AuthorAdminController
{
    public function __construct(
        private readonly AuthorRepositoryInterface $authorRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/admin/blog/authors')]
    #[RequiresPermission('blog.authors.view')]
    public function index(
        Request $request,
    ): Response {
        $page = (int) ($request->query('page', '1'));

        if ($page < 1) {
            $page = 1;
        }

        $perPage = $this->paginationService->getPerPage();
        $allAuthors = $this->authorRepository->findAll();
        $totalAuthors = count($allAuthors);

        $offset = $this->paginationService->calculateOffset($page);
        $authors = array_slice($allAuthors, $offset, $perPage);

        $pagination = $this->paginationService->paginate($authors, $totalAuthors, $page);

        return $this->view->render('blog::admin/author/index', [
            'authors' => $pagination,
        ]);
    }

    #[Get('/admin/blog/authors/create')]
    #[RequiresPermission('blog.authors.create')]
    public function create(): Response
    {
        return $this->view->render('blog::admin/author/create');
    }

    #[PostRoute('/admin/blog/authors')]
    #[RequiresPermission('blog.authors.create')]
    public function store(
        Request $request,
    ): Response {
        $name = (string) $request->post('name', '');
        $email = (string) $request->post('email', '');
        $bio = $request->post('bio');

        $errors = $this->validateAuthorData($name, $email);

        if ($errors !== []) {
            return $this->view->render('blog::admin/author/create', [
                'errors' => $errors,
                'input' => $request->post(),
            ]);
        }

        $author = new Author();
        $author->name = $name;
        $author->email = $email;
        $author->bio = $bio !== '' ? $bio : null;
        $author->slug = $this->slugGenerator->generate(
            $name,
            fn (string $slug): bool => $this->authorRepository->isSlugUnique($slug),
        );

        $this->authorRepository->save($author);

        $this->eventDispatcher->dispatch(new AuthorCreated(
            author: $author,
            timestamp: new DateTimeImmutable(),
        ));

        return Response::redirect('/admin/blog/authors/' . $author->id . '/edit');
    }

    #[Get('/admin/blog/authors/{id}/edit')]
    #[RequiresPermission('blog.authors.edit')]
    public function edit(
        int $id,
    ): Response {
        $author = $this->authorRepository->find($id);

        if ($author === null) {
            return new Response('Author not found', 404);
        }

        return $this->view->render('blog::admin/author/edit', [
            'author' => $author,
        ]);
    }

    #[Put('/admin/blog/authors/{id}')]
    #[RequiresPermission('blog.authors.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $author = $this->authorRepository->find($id);

        if ($author === null) {
            return new Response('Author not found', 404);
        }

        $name = (string) $request->post('name', '');
        $email = (string) $request->post('email', '');
        $bio = $request->post('bio');

        $errors = $this->validateAuthorData($name, $email);

        if ($errors !== []) {
            return $this->view->render('blog::admin/author/edit', [
                'errors' => $errors,
                'author' => $author,
                'input' => $request->post(),
            ]);
        }

        /** @var Author $author */
        $author->name = $name;
        $author->email = $email;
        $author->bio = $bio !== '' ? $bio : null;

        $this->authorRepository->save($author);

        $this->eventDispatcher->dispatch(new AuthorUpdated(
            author: $author,
            timestamp: new DateTimeImmutable(),
        ));

        return Response::redirect('/admin/blog/authors/' . $author->id . '/edit');
    }

    #[Delete('/admin/blog/authors/{id}')]
    #[RequiresPermission('blog.authors.delete')]
    public function destroy(
        int $id,
    ): Response {
        $author = $this->authorRepository->find($id);

        if ($author === null) {
            return new Response('Author not found', 404);
        }

        $this->authorRepository->delete($author);

        $this->eventDispatcher->dispatch(new AuthorDeleted(
            author: $author,
            timestamp: new DateTimeImmutable(),
        ));

        return Response::redirect('/admin/blog/authors');
    }

    /**
     * @return array<string>
     */
    private function validateAuthorData(
        string $name,
        string $email,
    ): array {
        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        if ($email === '') {
            $errors[] = 'Email is required';
        }

        return $errors;
    }
}
