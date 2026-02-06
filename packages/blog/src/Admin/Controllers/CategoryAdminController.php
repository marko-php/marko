<?php

declare(strict_types=1);

namespace Marko\Blog\Admin\Controllers;

use Marko\AdminAuth\Attributes\RequiresPermission;
use Marko\AdminAuth\Middleware\AdminAuthMiddleware;
use Marko\Blog\Entity\Category;
use Marko\Blog\Events\Category\CategoryCreated;
use Marko\Blog\Events\Category\CategoryDeleted;
use Marko\Blog\Events\Category\CategoryUpdated;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
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
class CategoryAdminController
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly PaginationServiceInterface $paginationService,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/admin/blog/categories')]
    #[RequiresPermission('blog.categories.view')]
    public function index(
        Request $request,
    ): Response {
        $page = (int) ($request->query('page', '1'));

        if ($page < 1) {
            $page = 1;
        }

        $perPage = $this->paginationService->getPerPage();
        $allCategories = $this->categoryRepository->findAll();
        $totalCategories = count($allCategories);

        $offset = $this->paginationService->calculateOffset($page);
        $categories = array_slice($allCategories, $offset, $perPage);

        $pagination = $this->paginationService->paginate($categories, $totalCategories, $page);

        return $this->view->render('blog::admin/category/index', [
            'categories' => $pagination,
        ]);
    }

    #[Get('/admin/blog/categories/create')]
    #[RequiresPermission('blog.categories.create')]
    public function create(): Response
    {
        return $this->view->render('blog::admin/category/create', [
            'categories' => $this->categoryRepository->findAll(),
        ]);
    }

    #[PostRoute('/admin/blog/categories')]
    #[RequiresPermission('blog.categories.create')]
    public function store(
        Request $request,
    ): Response {
        $name = (string) $request->post('name', '');
        $parentId = $request->post('parent_id', '');

        $errors = $this->validateCategoryData($name);

        if ($errors !== []) {
            return $this->view->render('blog::admin/category/create', [
                'errors' => $errors,
                'input' => $request->post(),
                'categories' => $this->categoryRepository->findAll(),
            ]);
        }

        $category = new Category();
        $category->name = $name;
        $category->parentId = $parentId !== '' ? (int) $parentId : null;
        $category->slug = $this->slugGenerator->generate(
            $name,
            fn (string $slug): bool => $this->categoryRepository->isSlugUnique($slug),
        );

        $this->categoryRepository->save($category);

        $this->eventDispatcher->dispatch(new CategoryCreated(
            category: $category,
        ));

        return Response::redirect('/admin/blog/categories/' . $category->id . '/edit');
    }

    #[Get('/admin/blog/categories/{id}/edit')]
    #[RequiresPermission('blog.categories.edit')]
    public function edit(
        int $id,
    ): Response {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return new Response('Category not found', 404);
        }

        return $this->view->render('blog::admin/category/edit', [
            'category' => $category,
            'categories' => $this->categoryRepository->findAll(),
        ]);
    }

    #[Put('/admin/blog/categories/{id}')]
    #[RequiresPermission('blog.categories.edit')]
    public function update(
        int $id,
        Request $request,
    ): Response {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return new Response('Category not found', 404);
        }

        $name = (string) $request->post('name', '');
        $parentId = $request->post('parent_id', '');

        $errors = $this->validateCategoryData($name);

        if ($errors !== []) {
            return $this->view->render('blog::admin/category/edit', [
                'errors' => $errors,
                'category' => $category,
                'input' => $request->post(),
                'categories' => $this->categoryRepository->findAll(),
            ]);
        }

        /** @var Category $category */
        $category->name = $name;
        $category->parentId = $parentId !== '' ? (int) $parentId : null;

        $this->categoryRepository->save($category);

        $this->eventDispatcher->dispatch(new CategoryUpdated(
            category: $category,
        ));

        return Response::redirect('/admin/blog/categories/' . $category->id . '/edit');
    }

    #[Delete('/admin/blog/categories/{id}')]
    #[RequiresPermission('blog.categories.delete')]
    public function destroy(
        int $id,
    ): Response {
        $category = $this->categoryRepository->find($id);

        if ($category === null) {
            return new Response('Category not found', 404);
        }

        $this->categoryRepository->delete($category);

        $this->eventDispatcher->dispatch(new CategoryDeleted(
            category: $category,
        ));

        return Response::redirect('/admin/blog/categories');
    }

    /**
     * @return array<string>
     */
    private function validateCategoryData(
        string $name,
    ): array {
        $errors = [];

        if ($name === '') {
            $errors[] = 'Name is required';
        }

        return $errors;
    }
}
