<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Controllers;

use DateTimeImmutable;
use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Tag;
use Marko\Blog\Events\Tag\TagCreated;
use Marko\Blog\Events\Tag\TagDeleted;
use Marko\Blog\Events\Tag\TagUpdated;
use Marko\Blog\Repositories\TagRepositoryInterface;
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
class TagAdminController
{
    public function __construct(
        private readonly TagRepositoryInterface $tagRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/admin/blog/tags')]
    #[RequiresPermission('blog.tags.view')]
    public function index(
        Request $request,
    ): Response {
        $page = (int) ($request->query('page', '1'));

        if ($page < 1) {
            $page = 1;
        }

        $perPage = $this->paginationService->getPerPage();
        $allTags = $this->tagRepository->findAll();
        $totalTags = count($allTags);

        $offset = $this->paginationService->calculateOffset($page);
        $tags = array_slice($allTags, $offset, $perPage);

        $pagination = $this->paginationService->paginate($tags, $totalTags, $page);

        return $this->view->render('blog::admin/tag/index', [
            'tags' => $pagination,
        ]);
    }

    #[Get('/admin/blog/tags/create')]
    #[RequiresPermission('blog.tags.create')]
    public function create(): Response
    {
        return $this->view->render('blog::admin/tag/create');
    }

    #[PostRoute('/admin/blog/tags')]
    #[RequiresPermission('blog.tags.create')]
    public function store(
        Request $request,
    ): Response {
        $name = (string) $request->post('name', '');

        $errors = $this->validateTagData($name);

        if ($errors !== []) {
            return $this->view->render('blog::admin/tag/create', [
                'errors' => $errors,
                'input' => $request->post(),
            ]);
        }

        $tag = new Tag();
        $tag->name = $name;
        $tag->slug = $this->slugGenerator->generate(
            $name,
            fn (string $slug): bool => $this->tagRepository->isSlugUnique($slug),
        );

        $this->tagRepository->save($tag);

        $this->eventDispatcher->dispatch(new TagCreated(
            tag: $tag,
            timestamp: new DateTimeImmutable(),
        ));

        return Response::redirect('/admin/blog/tags/' . $tag->id . '/edit');
    }

    #[Get('/admin/blog/tags/{id}/edit')]
    #[RequiresPermission('blog.tags.edit')]
    public function edit(
        int $id,
    ): Response {
        $tag = $this->tagRepository->find($id);

        if ($tag === null) {
            return new Response('Tag not found', 404);
        }

        return $this->view->render('blog::admin/tag/edit', [
            'tag' => $tag,
        ]);
    }

    #[Put('/admin/blog/tags/{id}')]
    #[RequiresPermission('blog.tags.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $tag = $this->tagRepository->find($id);

        if ($tag === null) {
            return new Response('Tag not found', 404);
        }

        $name = (string) $request->post('name', '');

        $errors = $this->validateTagData($name);

        if ($errors !== []) {
            return $this->view->render('blog::admin/tag/edit', [
                'errors' => $errors,
                'tag' => $tag,
                'input' => $request->post(),
            ]);
        }

        /** @var Tag $tag */
        $tag->name = $name;

        $this->tagRepository->save($tag);

        $this->eventDispatcher->dispatch(new TagUpdated(
            tag: $tag,
            timestamp: new DateTimeImmutable(),
        ));

        return Response::redirect('/admin/blog/tags/' . $tag->id . '/edit');
    }

    #[Delete('/admin/blog/tags/{id}')]
    #[RequiresPermission('blog.tags.delete')]
    public function destroy(
        int $id,
    ): Response {
        $tag = $this->tagRepository->find($id);

        if ($tag === null) {
            return new Response('Tag not found', 404);
        }

        $this->tagRepository->delete($tag);

        $this->eventDispatcher->dispatch(new TagDeleted(
            tag: $tag,
            timestamp: new DateTimeImmutable(),
        ));

        return Response::redirect('/admin/blog/tags');
    }

    /**
     * @return array<string>
     */
    private function validateTagData(
        string $name,
    ): array {
        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        return $errors;
    }
}
