<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SearchServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class SearchController
{
    public function __construct(
        private readonly SearchServiceInterface $searchService,
        private readonly PaginationServiceInterface $paginationService,
        private readonly ViewInterface $view,
    ) {}

    #[Get('/blog/search')]
    public function index(
        string $q,
        int $page = 1,
    ): Response {
        $perPage = $this->paginationService->getPerPage();
        $offset = $this->paginationService->calculateOffset($page);
        $searchResult = $this->searchService->searchPaginated($q, $perPage, $offset);

        $paginatedResult = $this->paginationService->paginate(
            items: $searchResult['results'],
            totalItems: $searchResult['total'],
            currentPage: $page,
        );

        return $this->view->render('blog::search/index', [
            'results' => $paginatedResult,
            'query' => $q,
        ]);
    }
}
