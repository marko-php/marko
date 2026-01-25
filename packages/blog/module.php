<?php

declare(strict_types=1);

use Marko\Blog\Config\BlogConfig;
use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Repositories\AuthorRepository;
use Marko\Blog\Repositories\AuthorRepositoryInterface;
use Marko\Blog\Repositories\CategoryRepository;
use Marko\Blog\Repositories\CategoryRepositoryInterface;
use Marko\Blog\Repositories\CommentRepository;
use Marko\Blog\Repositories\CommentRepositoryInterface;
use Marko\Blog\Repositories\PostRepository;
use Marko\Blog\Repositories\PostRepositoryInterface;
use Marko\Blog\Repositories\TagRepository;
use Marko\Blog\Repositories\TagRepositoryInterface;
use Marko\Blog\Repositories\TokenRepository;
use Marko\Blog\Services\CommentRateLimiter;
use Marko\Blog\Services\CommentRateLimiterInterface;
use Marko\Blog\Services\CommentVerificationService;
use Marko\Blog\Services\CommentVerificationServiceInterface;
use Marko\Blog\Services\HoneypotValidator;
use Marko\Blog\Services\HoneypotValidatorInterface;
use Marko\Blog\Services\PaginationService;
use Marko\Blog\Services\PaginationServiceInterface;
use Marko\Blog\Services\SearchService;
use Marko\Blog\Services\SearchServiceInterface;
use Marko\Blog\Services\SeoMetaService;
use Marko\Blog\Services\SeoMetaServiceInterface;
use Marko\Blog\Services\SlugGenerator;
use Marko\Blog\Services\SlugGeneratorInterface;
use Marko\Blog\Services\TokenRepositoryInterface;

return [
    'bindings' => [
        BlogConfigInterface::class => BlogConfig::class,
        SlugGeneratorInterface::class => SlugGenerator::class,
        AuthorRepositoryInterface::class => AuthorRepository::class,
        CategoryRepositoryInterface::class => CategoryRepository::class,
        TagRepositoryInterface::class => TagRepository::class,
        PostRepositoryInterface::class => PostRepository::class,
        CommentRepositoryInterface::class => CommentRepository::class,
        TokenRepositoryInterface::class => TokenRepository::class,
        CommentVerificationServiceInterface::class => CommentVerificationService::class,
        CommentRateLimiterInterface::class => CommentRateLimiter::class,
        HoneypotValidatorInterface::class => HoneypotValidator::class,
        PaginationServiceInterface::class => PaginationService::class,
        SearchServiceInterface::class => SearchService::class,
        SeoMetaServiceInterface::class => SeoMetaService::class,
    ],
];
