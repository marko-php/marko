<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Module;

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
use ReflectionClass;

describe('Blog module.php bindings', function (): void {
    it('binds BlogConfigInterface to BlogConfig', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';

        expect(file_exists($modulePath))->toBeTrue();

        $config = require $modulePath;

        expect($config)->toHaveKey('bindings')
            ->and($config['bindings'])->toHaveKey(BlogConfigInterface::class)
            ->and($config['bindings'][BlogConfigInterface::class])->toBe(BlogConfig::class);
    });

    it('binds SlugGeneratorInterface to SlugGenerator', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(SlugGeneratorInterface::class)
            ->and($config['bindings'][SlugGeneratorInterface::class])->toBe(SlugGenerator::class);
    });

    it('binds AuthorRepositoryInterface to AuthorRepository', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(AuthorRepositoryInterface::class)
            ->and($config['bindings'][AuthorRepositoryInterface::class])->toBe(AuthorRepository::class);
    });

    it('binds CategoryRepositoryInterface to CategoryRepository', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(CategoryRepositoryInterface::class)
            ->and($config['bindings'][CategoryRepositoryInterface::class])->toBe(CategoryRepository::class);
    });

    it('binds TagRepositoryInterface to TagRepository', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(TagRepositoryInterface::class)
            ->and($config['bindings'][TagRepositoryInterface::class])->toBe(TagRepository::class);
    });

    it('binds PostRepositoryInterface to PostRepository', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(PostRepositoryInterface::class)
            ->and($config['bindings'][PostRepositoryInterface::class])->toBe(PostRepository::class);
    });

    it('binds CommentRepositoryInterface to CommentRepository', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(CommentRepositoryInterface::class)
            ->and($config['bindings'][CommentRepositoryInterface::class])->toBe(CommentRepository::class);
    });

    it('binds VerificationTokenRepositoryInterface to VerificationTokenRepository', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(TokenRepositoryInterface::class)
            ->and($config['bindings'][TokenRepositoryInterface::class])->toBe(TokenRepository::class);
    });

    it('binds CommentVerificationServiceInterface to CommentVerificationService', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(CommentVerificationServiceInterface::class)
            ->and($config['bindings'][CommentVerificationServiceInterface::class])->toBe(
                CommentVerificationService::class
            );
    });

    it('binds CommentRateLimiterInterface to CommentRateLimiter', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(CommentRateLimiterInterface::class)
            ->and($config['bindings'][CommentRateLimiterInterface::class])->toBe(CommentRateLimiter::class);
    });

    it('binds HoneypotValidatorInterface to HoneypotValidator', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(HoneypotValidatorInterface::class)
            ->and($config['bindings'][HoneypotValidatorInterface::class])->toBe(HoneypotValidator::class);
    });

    it('binds PaginationServiceInterface to PaginationService', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(PaginationServiceInterface::class)
            ->and($config['bindings'][PaginationServiceInterface::class])->toBe(PaginationService::class);
    });

    it('binds SearchServiceInterface to SearchService', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(SearchServiceInterface::class)
            ->and($config['bindings'][SearchServiceInterface::class])->toBe(SearchService::class);
    });

    it('binds SeoMetaServiceInterface to SeoMetaService', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config['bindings'])->toHaveKey(SeoMetaServiceInterface::class)
            ->and($config['bindings'][SeoMetaServiceInterface::class])->toBe(SeoMetaService::class);
    });
});

describe('Blog module composer.json dependencies', function (): void {
    it(
        'declares module dependencies on marko/core marko/routing marko/database marko/view marko/cache marko/mail marko/config marko/session',
        function (): void {
            $composerPath = dirname(__DIR__, 2) . '/composer.json';
    
            expect(file_exists($composerPath))->toBeTrue();
    
            $composer = json_decode(file_get_contents($composerPath), true);
    
            expect($composer['require'])->toHaveKey('marko/core')
                ->and($composer['require'])->toHaveKey('marko/routing')
                ->and($composer['require'])->toHaveKey('marko/database')
                ->and($composer['require'])->toHaveKey('marko/view')
                ->and($composer['require'])->toHaveKey('marko/cache')
                ->and($composer['require'])->toHaveKey('marko/mail')
                ->and($composer['require'])->toHaveKey('marko/config')
                ->and($composer['require'])->toHaveKey('marko/session');
        }
    );

    it('suggests marko/view-latte for default templates', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->toHaveKey('suggest')
            ->and($composer['suggest'])->toHaveKey('marko/view-latte');
    });

    it('suggests marko/csrf for CSRF protection on comment forms', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer)->toHaveKey('suggest')
            ->and($composer['suggest'])->toHaveKey('marko/csrf');
    });

    it('does not require specific drivers allowing custom implementations', function (): void {
        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode(file_get_contents($composerPath), true);

        expect($composer['require'])->not->toHaveKey('marko/database-mysql')
            ->and($composer['require'])->not->toHaveKey('marko/database-pgsql')
            ->and($composer['require'])->not->toHaveKey('marko/cache-file')
            ->and($composer['require'])->not->toHaveKey('marko/session-file');
    });

    it('allows all bindings to be overridden via Preferences', function (): void {
        $modulePath = dirname(__DIR__, 2) . '/module.php';
        $config = require $modulePath;

        expect($config)->toHaveKey('bindings')
            ->and($config['bindings'])->toBeArray();

        // Verify that bindings are simple class mappings (not closures or final classes)
        // This ensures they can be overridden via Preferences
        foreach ($config['bindings'] as $interface => $implementation) {
            // Each implementation should be a string (class name), not a closure
            expect($implementation)->toBeString()
                ->and(class_exists($implementation) || interface_exists($implementation))->toBeTrue();

            // Check that the implementation class is not final (allows extension via Preferences)
            if (class_exists($implementation)) {
                $reflection = new ReflectionClass($implementation);
                expect($reflection->isFinal())->toBeFalse(
                    "Class $implementation must not be final to allow Preference overrides",
                );
            }
        }
    });
});
