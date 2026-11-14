---
title: Coming From Laravel
description: Map Laravel concepts --- facades, service providers, Eloquent, Blade, Artisan and more --- to their Marko equivalents.
---

This page is for Laravel developers. Much of Marko will feel familiar: attribute routes, middleware, a fluent query builder, queued jobs, a scheduler, Pest tests and fakes. The difference is that Marko leaves out Laravel's implicit machinery. There are no facades, no global helpers, no magic properties and no auto-resolving model bindings. Every dependency is injected through a constructor, every class is found through an attribute or `module.php`, and errors are thrown instead of falling back to a default. Where Marko behaves differently from what you expect, this page says so.

Each section shows the Laravel code first, then the Marko code that does the same job.

## Quick Reference

| Laravel | Marko | Details |
|---|---|---|
| Facades and global helpers | Constructor injection | [Facades and Helpers](#facades-and-helpers) |
| Service providers | `module.php` | [Service Providers](#service-providers) |
| Packages and `app/` | Modules | [Packages and Modules](#packages-and-modules) |
| Extending vendor code | Preferences and plugins | [Changing Vendor Code](#changing-vendor-code) |
| `routes/web.php` | Route attributes on controllers | [Routes and Controllers](#routes-and-controllers) |
| Route model binding | Not supported | [Route Model Binding](#route-model-binding) |
| Middleware | `#[Middleware]` and `globalMiddleware` | [Middleware](#middleware) |
| Eloquent models | Entities and repositories | [Eloquent and Entities](#eloquent-and-entities) |
| Migrations | Generated from entities | [Migrations](#migrations) |
| Eloquent relationships | Relationship attributes, eager loading only | [Relationships](#relationships) |
| `$request->validate()`, form requests | `ValidatorInterface` | [Validation](#validation) |
| Blade | Latte or Twig | [Views](#views) |
| `config()`, `env()` | `ConfigRepositoryInterface`, `Env` | [Configuration](#configuration) |
| Events and listeners | Event classes and `#[Observer]` | [Events and Listeners](#events-and-listeners) |
| Queued jobs | `Job` and `QueueInterface` | [Queues](#queues) |
| Task scheduling | `Schedule` in a `boot` callback | [Scheduling](#scheduling) |
| Artisan commands | `#[Command]` and the `marko` CLI | [Console Commands](#console-commands) |
| `Auth`, `auth` middleware | `AuthManager`, `AuthMiddleware` | [Authentication](#authentication) |
| Gates and policies | `GateInterface`, `#[Can]` | [Authorization](#authorization) |
| HTTP tests, `RefreshDatabase`, factories, fakes | `TestClient`, `RefreshDatabase`, `EntityFactory`, fakes | [Testing](#testing) |

## Facades and Helpers

In Laravel, a facade or a global helper reaches into the container from anywhere:

```php title="app/Services/PostService.php"
<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PostService
{
    public function popular(): array
    {
        return Cache::remember('posts.popular', 3600, fn () => $this->fetchPopular());
    }
}
```

Marko has no facades and no global helpers: no `cache()`, `config()`, `route()`, `view()`, `auth()`, `event()`, `dispatch()` or `app()`. A class declares what it needs in its constructor, and the container autowires it:

```php title="app/blog/src/Service/PostService.php"
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\Cache\Contracts\CacheInterface;

readonly class PostService
{
    public function __construct(
        private CacheInterface $cache,
    ) {}

    public function popular(): array
    {
        return $this->cache->remember('posts.popular', 3600, fn () => $this->fetchPopular());
    }
}
```

This is the biggest change in habit, and it is deliberate. A class's dependencies are all in its constructor, so you can see them, PHPStan checks them, and a test passes a fake in directly instead of swapping a facade. The table below lists where each common facade's job went:

| Laravel | Inject |
|---|---|
| `Cache` | `Marko\Cache\Contracts\CacheInterface` |
| `Config`, `config()` | `Marko\Config\ConfigRepositoryInterface` |
| `DB` | `Marko\Database\Query\QueryBuilderInterface` |
| `Event`, `event()` | `Marko\Core\Event\EventDispatcherInterface` |
| `Queue`, `dispatch()` | `Marko\Queue\QueueInterface` |
| `Mail` | `Marko\Mail\Contracts\MailerInterface` |
| `Log`, `logger()` | `Marko\Log\Contracts\LoggerInterface` |
| `Auth`, `auth()` | `Marko\Authentication\AuthManager` |
| `Gate` | `Marko\Authorization\Contracts\GateInterface` |
| `View`, `view()` | `Marko\View\ViewInterface` |
| `URL`, `route()` | `Marko\Routing\UrlGeneratorInterface` |
| `Hash` | `Marko\Hashing\Contracts\HasherInterface` |

There is no `app()->make()` either. Ask for the dependency in the constructor; if a class needs services plus values known only at runtime, write a small factory class that injects the services.

See [Dependency Injection](/docs/concepts/dependency-injection/).

## Service Providers

In Laravel, a service provider binds interfaces in `register()` and runs setup in `boot()`:

```php title="app/Providers/PaymentServiceProvider.php"
<?php

declare(strict_types=1);

namespace App\Providers;

use App\Payments\PaymentGatewayInterface;
use App\Payments\StripePaymentGateway;
use Illuminate\Support\ServiceProvider;

class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGatewayInterface::class, StripePaymentGateway::class);
    }

    public function boot(): void
    {
        // ...
    }
}
```

In Marko, each module's `module.php` does this job. `bindings` maps interfaces to implementations, `singletons` does the same for shared instances, and `boot` runs after every module's bindings are registered, with its parameters injected:

```php title="app/payments/module.php"
<?php

declare(strict_types=1);

use App\Payments\PaymentGatewayInterface;
use App\Payments\StripePaymentGateway;
use Marko\Core\Environment\AppEnvironment;

return [
    'singletons' => [
        PaymentGatewayInterface::class => StripePaymentGateway::class,
    ],
    'boot' => function (AppEnvironment $appEnvironment): void {
        // ...
    },
];
```

There is no provider list to register in `config/app.php` or `bootstrap/providers.php`; `module.php` is read automatically. The `arguments` key sets individual constructor arguments, which replaces Laravel's contextual binding (`when()->needs()->give()`).

Two modules at the same priority binding the same interface throw `BindingConflictException` at boot. Laravel lets the last provider win silently.

See [Creating Modules](/docs/packages/core/#creating-modules) and [Argument Substitution](/docs/concepts/dependency-injection/#argument-substitution).

## Packages and Modules

A Laravel app has one `app/` directory, and packages hook in through auto-discovered service providers. In Marko, everything is a module: your own code and every vendor package alike. A module is a Composer package whose `composer.json` declares the module flag:

```json title="app/blog/composer.json"
{
    "name": "app/blog",
    "autoload": {
        "psr-4": {
            "App\\Blog\\": "src/"
        }
    },
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

Your application is split into modules under `app/` (`app/blog/`, `app/billing/`), each with its own `src/`, `config/`, `resources/views/` and `module.php`. Modules are discovered in `vendor/`, `modules/` and `app/`, and priority runs in that order: `app/` beats `modules/`, which beats `vendor/`.

Load order comes from `composer.json` `require`: a module always loads after the modules it requires.

See [Modularity](/docs/concepts/modularity/) and [Project Structure](/docs/getting-started/project-structure/).

## Changing Vendor Code

Laravel has no general way to change how a vendor class behaves. You rebind its interface in a provider if it has one, or fork the package. Marko has two tools for this, borrowed from Magento.

A **Preference** replaces a concrete class everywhere it is injected:

```php title="app/blog/src/Controller/CustomPostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use Acme\Blog\Controller\PostController;
use Marko\Core\Attributes\Preference;

#[Preference(replaces: PostController::class)]
class CustomPostController extends PostController
{
}
```

A **plugin** runs code before or after a method of another class, without replacing it:

```php title="app/seo/src/Plugin/PostSeoPlugin.php"
<?php

declare(strict_types=1);

namespace App\Seo\Plugin;

use Acme\Blog\Repository\PostRepositoryInterface;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: PostRepositoryInterface::class)]
class PostSeoPlugin
{
    #[Before]
    public function save(object $post): null
    {
        // runs before every PostRepositoryInterface::save()
        return null;
    }
}
```

Both are discovered from their attributes, with no registration. This is why Marko's own packages are built from small interfaces: any of them can be swapped or wrapped from your `app/` modules.

See [Preferences](/docs/concepts/preferences/) and [Plugins](/docs/concepts/plugins/).

## Routes and Controllers

In Laravel, routes live in `routes/web.php` and point at controller methods:

```php title="routes/web.php"
<?php

declare(strict_types=1);

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

Route::prefix('blog')->name('blog.')->group(function () {
    Route::get('/posts/{id}', [PostController::class, 'show'])->name('posts.show');
    Route::post('/posts', [PostController::class, 'store'])->name('posts.store');
});
```

In Marko, there are no route files. The route is an attribute on the controller method, and `#[RoutePrefix]` replaces `Route::prefix()->name()->group()`:

```php title="app/blog/src/Controller/PostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use Marko\Routing\Attributes\FromBody;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Attributes\RoutePrefix;
use Marko\Routing\Http\Response;
use Marko\Routing\UrlGeneratorInterface;

#[RoutePrefix('/blog', namePrefix: 'blog.')]
class PostController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    #[Get('/posts/{id}', name: 'posts.show')]
    public function show(int $id): Response
    {
        return new Response('Post ' . $id);
    }

    #[Post('/posts', name: 'posts.store')]
    public function store(#[FromBody] string $title): Response
    {
        $id = 1; // ...create the post

        return Response::redirect($this->urlGenerator->route('blog.posts.show', ['id' => $id]));
    }
}
```

Differences from Laravel:

- **Request input does not reach a parameter by name alone.** Route parameters bind by name, but a query or body field binds only when the parameter opts in with `#[FromQuery]`, `#[FromBody]` or `#[FromInput]`. Type-hint `Marko\Routing\Http\Request` to read it yourself with `query()`, `post()`, `input()` or `json()`.
- **Values are converted strictly.** `/blog/posts/abc` for `int $id` is a `400`, not a `TypeError`.
- **There is no `route()` helper.** Inject `UrlGeneratorInterface`. Templates get a `route()` function.
- **Conflicts fail at boot.** Two controllers claiming the same path and method, or two routes with the same name, throw instead of the later one winning.

`marko route:list` is the equivalent of `php artisan route:list`.

See [Routing](/docs/guides/routing/) and the [Routing package](/docs/packages/routing/).

## Route Model Binding

Laravel resolves `Post $post` from the `{post}` route segment. Marko has no route model binding: take the ID and load the entity from its repository, so the query is visible in the controller:

```php title="app/blog/src/Controller/PostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use App\Blog\Repository\PostRepository;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

readonly class PostController
{
    public function __construct(
        private PostRepository $postRepository,
        private ViewInterface $view,
    ) {}

    #[Get('/posts/{id}')]
    public function show(int $id): Response
    {
        return $this->view->render('blog::post/show', [
            'post' => $this->postRepository->findOrFail($id),
        ]);
    }
}
```

## Middleware

In Laravel, global middleware is appended in `bootstrap/app.php`, and route middleware is attached by alias:

```php title="routes/web.php"
<?php

declare(strict_types=1);

use App\Http\Controllers\AdminController;
use Illuminate\Support\Facades\Route;

Route::get('/admin', [AdminController::class, 'index'])->middleware('auth');
```

In Marko, route middleware is an attribute that names the class. There are no string aliases. On a class, it applies to every route in it:

```php title="app/admin/src/Controller/AdminController.php"
<?php

declare(strict_types=1);

namespace App\Admin\Controller;

use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;

#[Middleware(AuthMiddleware::class)]
class AdminController
{
    #[Get('/admin')]
    public function index(): Response
    {
        return new Response('Admin');
    }
}
```

Global middleware is declared by the module that provides it, under `globalMiddleware` in its `module.php`. Installing `marko/session-file` adds the session middleware to every route, for example. `#[WithoutMiddleware]` replaces Laravel's `withoutMiddleware()`, and it throws at boot when the middleware it names is not on the route, so a typo can't leave the middleware running.

A middleware implements `Marko\Routing\Middleware\MiddlewareInterface` with a `handle(Request $request, callable $next): Response` method, as in Laravel.

See [Adding Middleware](/docs/packages/routing/#adding-middleware) and [Skipping Middleware](/docs/packages/routing/#skipping-middleware).

## Eloquent and Entities

In Laravel, an Eloquent model is Active Record: it holds a row, its columns are magic properties defined by the [migration](#migrations), and it saves and queries itself. A local scope adds a reusable query:

```php title="app/Models/Post.php"
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    protected $fillable = ['title', 'body'];

    public function scopeTitled(
        Builder $query,
        string $title,
    ): void {
        $query->where('title', $title);
    }
}
```

```php
$post = Post::create(['title' => 'Hello', 'body' => 'World']);

$post = Post::titled('Hello')->first();
```

Marko uses the Data Mapper pattern, which splits that model in two. An **entity** holds the data and knows nothing about the database. A **repository** loads and saves entities.

The entity is a plain class with typed, declared properties:

```php title="app/blog/src/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Attributes\Timestamps;
use Marko\Database\Entity\Entity;

#[Table('posts')]
#[Timestamps]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title;

    #[Column]
    public string $body;

    #[Column(type: 'timestamp')]
    public ?DateTimeImmutable $createdAt = null;

    #[Column(type: 'timestamp')]
    public ?DateTimeImmutable $updatedAt = null;
}
```

The Eloquent model has an `id`, `created_at` and `updated_at` too, but implicitly: Eloquent assumes an auto-incrementing `id` and turns on timestamps by default. A Marko entity declares every column. `#[Timestamps]` fills `createdAt` and `updatedAt` on save, but the entity must still declare both as `DateTimeImmutable` columns, or parsing it throws `EntityException`.

### Why There Is a Repository

An Eloquent model saves itself, so `Post::create()` and `Post::titled()` live on the model. A Marko entity can't save itself, so that job moves to a repository class. Every entity you load or save through the ORM needs one. `Repository` is abstract, and there is no generic repository to point at an entity class.

The smallest repository only names its entity:

```php title="app/blog/src/Repository/PostRepository.php"
<?php

declare(strict_types=1);

namespace App\Blog\Repository;

use App\Blog\Entity\Post;
use Marko\Database\Repository\Repository;

class PostRepository extends Repository
{
    protected const ENTITY_CLASS = Post::class;
}
```

That class already has `find()`, `findOrFail()`, `findBy()`, `findOneBy()`, `save()`, `delete()`, `count()`, `exists()`, `with()` for eager loading and `query()` for anything else. Reusable queries, which were scopes on the Eloquent model, become methods on the repository:

```php title="app/blog/src/Repository/PostRepository.php"
<?php

declare(strict_types=1);

namespace App\Blog\Repository;

use App\Blog\Entity\Post;
use Marko\Database\Repository\Repository;

class PostRepository extends Repository
{
    protected const ENTITY_CLASS = Post::class;

    public function findByTitle(string $title): ?Post
    {
        return $this->findOneBy(['title' => $title]);
    }
}
```

Code that needs posts injects the repository, here as `$postRepository`:

```php
$post = new Post();
$post->title = 'Hello';
$post->body = 'World';

$this->postRepository->save($post);

$post = $this->postRepository->findByTitle('Hello');
```

The extra class pays for itself:

- **Persistence is injected, not static.** A test can pass a different repository instead of working around `Post::titled()`.
- **Other modules can hook into it.** A [plugin](#changing-vendor-code) on `save()` runs on every save of a post, from any module.
- **Each entity's queries have one home.** They are not spread across controllers and model scopes.

To skip entities entirely, inject `QueryBuilderInterface` and work with arrays. See [Query Builder](/docs/guides/database/#query-builder).

What differs from Eloquent:

- **There are no magic properties or static query methods.** Columns are real properties, so IDEs and PHPStan see them. There is no `Post::where()`; queries go through the repository's `query()`, `find()`, `findBy()` and `findOneBy()`.
- **There is no mass assignment, so there is no `$fillable`.** Set properties one by one, from validated input.
- **Query scopes become repository methods**, as above, or reusable `QuerySpecification` objects passed to `matching()`.
- **`EntityCollection` is not Laravel's `Collection`.** It has `filter`, `map`, `sortBy`, `groupBy`, `chunk` and `pluck`, and Marko has no general-purpose collection class for arrays.

Model events such as `creating` and `created` become events like `EntityCreating` and `EntityCreated`, handled by [observers](#events-and-listeners).

See [Database](/docs/guides/database/) and the [Database package](/docs/packages/database/).

## Migrations

In Laravel, you write each migration by hand, and the schema is spread across the migration files:

```php title="database/migrations/2026_01_01_000000_create_posts_table.php"
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->timestamps();
        });
    }
};
```

In Marko, the entity is the schema. In development, `marko db:migrate` compares your entities with the database and writes the migration files for you, then applies them. You commit the generated files, and in every other environment `db:migrate` only applies them. `marko db:diff` previews the difference first.

| Laravel | Marko |
|---|---|
| `php artisan make:migration` + edit | Change the entity |
| `php artisan migrate` | `marko db:migrate` |
| `php artisan migrate:rollback` | `marko db:rollback` |
| `php artisan migrate:status` | `marko db:status` |
| `php artisan migrate:fresh` | `marko db:rebuild` |
| `php artisan db:seed` | `marko db:seed` |

Destructive commands need `--force` outside development and testing, and are always refused in production.

See [Migrations](/docs/guides/database/#migrations) and [Environment Behaviour](/docs/packages/database/#environment-behaviour).

## Relationships

In Laravel, a relationship is a method, and accessing it as a property lazy loads it:

```php title="app/Models/Post.php"
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

In Marko, a relationship is an attribute on a typed property:

```php title="app/blog/src/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\HasMany;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;
use Marko\Database\Entity\EntityCollection;

#[Table('posts')]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[HasMany(entityClass: Comment::class, foreignKey: 'postId')]
    public EntityCollection $comments;
}
```

`#[HasOne]`, `#[BelongsTo]` and `#[BelongsToMany]` cover the other shapes. There is **no lazy loading**: load relationships up front with `with()`, which is Laravel's eager loading, so an N+1 query can't happen by accident:

```php
$posts = $this->postRepository->with('comments.author')->findAll();
```

`whereHas()` and `whereDoesntHave()` filter on relationships, as in Eloquent.

See [Relationships](/docs/packages/database/#relationships).

## Validation

In Laravel, `$request->validate()` validates the request, returns the validated data and redirects back with errors on failure:

```php
$data = $request->validate([
    'title' => 'required|string|max:200',
    'email' => 'required|email',
]);
```

In Marko, inject `ValidatorInterface`. Rules use the same string or array formats, and many names match Laravel's (`required`, `email`, `min`, `max`, `in`, `confirmed`, `nullable`, `sometimes`):

```php title="app/blog/src/Controller/PostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Contracts\ValidatorInterface;

readonly class PostController
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {}

    #[Post('/posts')]
    public function store(Request $request): Response
    {
        $this->validator->validateOrFail($request->post(), [
            'title' => 'required|string|max:200',
            'email' => 'required|email',
        ]);

        // ...create the post from $request->post()

        return Response::redirect('/posts', 303);
    }
}
```

- `validateOrFail()` returns nothing; read the input from the request after it passes. `validate()` returns a `ValidationErrors` object instead of throwing.
- A failure is a `422` with the errors as JSON. To redirect HTML forms back with flashed errors and old input, as Laravel does, install [marko/forms](/docs/packages/forms/). It also adds the `old()`, `errors()` and `csrf_field()` template functions.
- There are no form request classes. Put shared rules in a class of your own and inject it.

See [Validation](/docs/guides/validation/) and the [Validation package](/docs/packages/validation/).

## Views

In Laravel, `view()` renders a Blade template from `resources/views`:

```php
return view('posts.show', ['post' => $post]);
```

In Marko, inject `ViewInterface`. Template names are `module::path`, so each module owns the templates in its own `resources/views/`:

```php
return $this->view->render('blog::post/show', ['post' => $post]);
```

There is no Blade. Install [marko/view-latte](/docs/packages/view-latte/) or [marko/view-twig](/docs/packages/view-twig/). Neither renders plain PHP templates. Laravel's view features map like this:

| Laravel | Marko |
|---|---|
| `View::share()`, view composers | A `#[ViewData]` class implementing `ViewDataProviderInterface` |
| Custom Blade directives | A `#[TemplateFunctions]` provider, which works in every engine |
| `@csrf`, `old()`, `$errors` | `csrf_field()`, `old()`, `$errors` from [marko/forms](/docs/packages/forms/) |
| `route()` in Blade | `route()` template function |
| Blade layouts and components | The engine's own layouts, or [marko/layout](/docs/guides/layout/) for cross-module page composition |
| Inertia | [marko/inertia](/docs/packages/inertia/) with React, Vue or Svelte |
| Vite | [marko/vite](/docs/packages/vite/) |

See the [View package](/docs/packages/view/).

## Configuration

In Laravel, `config/*.php` files can call `env()`, and code reads values with `config()`, falling back to a default:

```php
$perPage = config('blog.per_page', 10);
```

In Marko, each module ships its own `config/*.php` defaults, and the project-root `config/` directory overrides them. Config files read the environment through `Marko\Config\Env`, which parses the type:

```php title="app/blog/config/blog.php"
<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    'per_page' => Env::int('BLOG_PER_PAGE', 10),
];
```

Code injects `ConfigRepositoryInterface` and calls a typed accessor:

```php title="app/blog/src/Service/PostLimits.php"
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\Config\ConfigRepositoryInterface;

readonly class PostLimits
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    public function perPage(): int
    {
        return $this->configRepository->getInt('blog.per_page');
    }
}
```

There is no default argument at the call site: the default lives in the module's config file, and a missing key throws `ConfigNotFoundException`. Application code never reads the environment directly. There is no `config:cache` or `route:cache`: in production, `marko discovery:cache` compiles config and routes along with the rest of discovery.

`APP_ENV` works as in Laravel. Inject `Marko\Core\Environment\AppEnvironment` for `isProduction()`, `isDevelopment()` and `isTesting()`. An unset `APP_ENV` counts as production.

See [Configuration](/docs/getting-started/configuration/) and the [Config package](/docs/packages/config/).

## Events and Listeners

In Laravel, an event class is dispatched and a listener's `handle()` type-hint registers it. `ShouldQueue` moves the listener to the queue:

```php title="app/Listeners/TrackPostCreation.php"
<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostCreated;
use Illuminate\Contracts\Queue\ShouldQueue;

class TrackPostCreation implements ShouldQueue
{
    public function handle(PostCreated $event): void
    {
        // ...
    }
}
```

```php
PostCreated::dispatch($post);
```

In Marko, an event extends `Marko\Core\Event\Event`, and a listener is an **observer**: a class with `#[Observer]` naming the event, and a `handle()` method. `async: true` is the equivalent of `ShouldQueue`:

```php title="app/analytics/src/Observer/TrackPostCreation.php"
<?php

declare(strict_types=1);

namespace App\Analytics\Observer;

use App\Blog\Events\PostCreated;
use Marko\Core\Attributes\Observer;

#[Observer(event: PostCreated::class, async: true)]
class TrackPostCreation
{
    public function handle(PostCreated $event): void
    {
        // ...
    }
}
```

The dispatcher is injected, and accepts only event objects; there are no string-named events and no wildcard listeners:

```php
$this->eventDispatcher->dispatch(new PostCreated($post));
```

`priority` orders observers, highest first. There are no event subscribers; write one observer per event.

See [Events](/docs/concepts/events/).

## Queues

In Laravel, a job uses the `Dispatchable` and `Queueable` traits and dispatches itself:

```php
SendWelcomeEmail::dispatch($user->id);
```

In Marko, a job extends `Marko\Queue\Job` and implements `handle()`. Inject `QueueInterface` and push it:

```php title="app/blog/src/Job/SendWelcomeEmail.php"
<?php

declare(strict_types=1);

namespace App\Blog\Job;

use Marko\Queue\Job;

class SendWelcomeEmail extends Job
{
    public function __construct(
        private int $userId,
    ) {}

    public function handle(): void
    {
        // ...
    }
}
```

```php
$this->queue->push(new SendWelcomeEmail($userId));
```

`marko queue:work` runs the worker, and `queue:failed`, `queue:retry` and `queue:restart` match their Artisan counterparts. A driver package supplies the backend: `marko/queue-sync`, `marko/queue-database`, `marko/queue-redis` or `marko/queue-rabbitmq`.

See [Queues](/docs/guides/queues/).

## Scheduling

In Laravel, tasks are scheduled in `routes/console.php`:

```php title="routes/console.php"
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('blog:prune')->daily();
```

In Marko, inject `Schedule` into a `boot` callback in `module.php`. The frequency methods read the same:

```php title="app/blog/module.php"
<?php

declare(strict_types=1);

use Marko\Scheduler\Schedule;

return [
    'boot' => function (Schedule $schedule): void {
        $schedule->command('blog:prune')->daily();
    },
];
```

One system cron entry runs `marko schedule:run` every minute, as with `schedule:run` in Laravel. `marko schedule:work` runs the scheduler in the foreground during development.

See [Scheduling](/docs/guides/scheduling/).

## Console Commands

In Laravel, an Artisan command declares a `$signature` and implements `handle()`:

```php title="app/Console/Commands/PruneCommand.php"
<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PruneCommand extends Command
{
    protected $signature = 'blog:prune';

    protected $description = 'Delete old drafts';

    public function handle(): int
    {
        $this->info('Pruned.');

        return self::SUCCESS;
    }
}
```

In Marko, put `#[Command]` on a class that implements `CommandInterface`, and run it with `marko blog:prune`:

```php title="app/blog/src/Command/PruneCommand.php"
<?php

declare(strict_types=1);

namespace App\Blog\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'blog:prune', description: 'Delete old drafts')]
class PruneCommand implements CommandInterface
{
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $output->writeLine('Pruned.');

        return 0;
    }
}
```

Constructor dependencies are injected. The `marko` CLI is installed globally, so there is no `php artisan` prefix. `marko dev:up` from [marko/devserver](/docs/packages/devserver/) replaces `php artisan serve`, and also starts the other services your app needs.

Marko has no `make:*` generators and no `tinker`.

See [Registering Console Commands](/docs/packages/core/#registering-console-commands) and the [CLI package](/docs/packages/cli/).

## Authentication

In Laravel, the `Auth` facade logs users in, and the `auth` and `guest` middleware aliases protect routes:

```php title="app/Http/Controllers/LoginController.php"
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController
{
    public function store(Request $request): RedirectResponse
    {
        if (Auth::attempt($request->only('email', 'password'))) {
            return redirect('/dashboard');
        }

        return back()->withErrors(['email' => 'Invalid credentials.']);
    }
}
```

```php title="routes/web.php"
Route::post('/login', [LoginController::class, 'store'])->middleware('guest');
Route::get('/dashboard', [DashboardController::class, 'index'])->middleware('auth');
```

In Marko, inject `AuthManager`, which has the same `attempt()`, `check()`, `user()`, `id()` and `logout()` methods as the facade. `GuestMiddleware` and `AuthMiddleware` replace the `guest` and `auth` aliases:

```php title="app/account/src/Controller/LoginController.php"
<?php

declare(strict_types=1);

namespace App\Account\Controller;

use Marko\Authentication\AuthManager;
use Marko\Authentication\Middleware\GuestMiddleware;
use Marko\Routing\Attributes\FromBody;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Response;

readonly class LoginController
{
    public function __construct(
        private AuthManager $authManager,
    ) {}

    #[Post('/login')]
    #[Middleware(GuestMiddleware::class)]
    public function store(
        #[FromBody] string $email,
        #[FromBody] string $password,
    ): Response {
        if ($this->authManager->attempt(['email' => $email, 'password' => $password])) {
            return Response::redirect('/dashboard', 303);
        }

        return new Response('Invalid credentials.', 401);
    }
}
```

Guards and user providers are configured in `config/authentication.php`, much as in Laravel's `config/auth.php`. The big difference is where users come from:

- **There is no built-in `User` model or Eloquent user provider.** Laravel ships both. In Marko, your user class implements `AuthenticatableInterface`, and your module binds a `UserProviderInterface` implementation that loads users and checks passwords. See [Making Users Authenticatable](/docs/packages/authentication/#making-users-authenticatable) and [User Providers](/docs/packages/authentication/#user-providers).
- **`attempt()` is throttled by default.** Repeated failures for one login throw `TooManyLoginAttemptsException`. In Laravel, the starter kits add this rate limiting to their login request.
- **Token auth for APIs** comes from [marko/authentication-token](/docs/packages/authentication-token/), the counterpart of Sanctum's API tokens.

See [Authentication](/docs/guides/authentication/) and the [Authentication package](/docs/packages/authentication/).

## Authorization

Authorization answers two questions, and Laravel and Marko handle each one the same way:

- **Can this user do this at all?** For example, create a post. There is no record yet, so the check runs on the route, before the controller.
- **Can this user do this to this record?** For example, update post 42. The check needs the loaded post, so it runs inside the controller.

Both questions are answered by a policy, provided by [marko/authorization](/docs/packages/authorization/).

### Policies

In Laravel, a policy has one method per ability:

```php title="app/Policies/PostPolicy.php"
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

class PostPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    public function update(
        User $user,
        Post $post,
    ): bool {
        return $user->id === $post->user_id;
    }
}
```

A Marko policy has the same methods, with two differences:

```php title="app/blog/src/Policy/PostPolicy.php"
<?php

declare(strict_types=1);

namespace App\Blog\Policy;

use App\Blog\Entity\Post;
use Marko\Authorization\AuthorizableInterface;

class PostPolicy
{
    public function create(
        ?AuthorizableInterface $user,
        string $postClass,
    ): bool {
        return $user !== null;
    }

    public function update(
        ?AuthorizableInterface $user,
        Post $post,
    ): bool {
        return $user !== null
            && $user->getAuthIdentifier() === $post->authorId;
    }
}
```

- **Guests reach the policy.** Laravel denies a guest without calling a method whose `User $user` is not nullable. Marko always calls the method and passes `null` for a guest, so the policy checks for one itself.
- **`create()` receives the class name.** A check with no record passes the entity's class name as the second argument, here `Post::class`.

Your user class implements `AuthorizableInterface`, which extends `AuthenticatableInterface`.

Laravel finds `PostPolicy` by naming convention. Marko does not, so register each policy with the gate from a `boot` callback:

```php title="app/blog/module.php"
<?php

declare(strict_types=1);

use App\Blog\Entity\Post;
use App\Blog\Policy\PostPolicy;
use Marko\Authorization\Contracts\GateInterface;

return [
    'boot' => function (GateInterface $gate): void {
        $gate->policy(Post::class, PostPolicy::class);
    },
];
```

### Checking an Action

Use this when the ability does not depend on a record, such as `create`. In Laravel, the `can` middleware checks it on the route:

```php title="routes/web.php"
Route::get('/posts/create', [PostController::class, 'create'])->middleware('can:create,App\Models\Post');
```

In Marko, put `#[Can]` on the controller method. There is no middleware to attach: installing `marko/authorization` checks every route that has the attribute.

```php
#[Get('/posts/create')]
#[Can(ability: 'create', entityClass: Post::class)]
public function create(): Response
{
    return new Response('New post form');
}
```

### Checking a Record

Use this when the ability depends on one record, such as `update`. In Laravel, the controller loads the post and calls `Gate::authorize()`:

```php
public function update(int $id): Response
{
    $post = Post::findOrFail($id);
    Gate::authorize('update', $post);

    // ...
}
```

Marko is the same, with the gate and repository injected:

```php
#[Put('/posts/{id}')]
public function update(int $id): Response
{
    $post = $this->postRepository->findOrFail($id);
    $this->gate->authorize('update', $post);

    // ...
}
```

`#[Can]` can't do this check, because there is no [route model binding](#route-model-binding) to give it the post. `GateInterface` also has `allows()` and `denies()` for a check that returns a `bool` instead of throwing, as the `Gate` facade does.

### When a Check Fails

Failures are thrown, so the controller never handles them. A guest on a `#[Can]` route gets a `401`, and a logged-in user who is denied gets a `403`. A denied `authorize()` call is always a `403`, even for a guest.

See the [Authorization package](/docs/packages/authorization/).

## Testing

Marko uses Pest, and `marko/testing` covers what Laravel's testing layer does: HTTP tests, a database reset between tests, factories and fakes.

### Test Setup

In Laravel, `tests/Pest.php` extends the framework's `TestCase` and adds the `RefreshDatabase` trait, which wraps each test in a transaction:

```php title="tests/Pest.php"
<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');
```

Marko has no base `TestCase` to extend. `TestDatabase::boot()` boots the application and runs the migrations once per process. `RefreshDatabase` is a class rather than a trait: call `begin()` before each test and `rollback()` after it. `client()` returns an HTTP client that shares the test's transaction:

```php title="tests/Pest.php"
<?php

declare(strict_types=1);

use Marko\Testing\Database\RefreshDatabase;
use Marko\Testing\Database\TestDatabase;

uses()
    ->beforeEach(function () {
        $this->database = TestDatabase::boot(dirname(__DIR__));
        $this->refresh = new RefreshDatabase($this->database);
        $this->refresh->begin();
        $this->http = $this->database->client();
    })
    ->afterEach(function () {
        $this->refresh->rollback();
    })
    ->in('Feature');
```

`TruncateDatabase` empties the tables instead of rolling back, for code that needs committed data, such as a queue worker. See [Database Tests](/docs/packages/testing/#database-tests).

### HTTP Tests

In Laravel, a feature test calls `get()` and `post()` on the test case:

```php title="tests/Feature/DashboardTest.php"
<?php

declare(strict_types=1);

use App\Models\User;

it('shows the dashboard to a logged-in user', function () {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Welcome');
});
```

In Marko, the same calls go to the `TestClient` from the setup. It sends requests in process through the real router and middleware, and its assertion names match Laravel's (`assertOk()`, `assertRedirect()`, `assertJsonPath()`, `assertSessionHas()` and the rest):

```php title="tests/Feature/DashboardTest.php"
<?php

declare(strict_types=1);

use Marko\Testing\Fake\FakeAuthenticatable;

it('shows the dashboard to a logged-in user', function () {
    $this->http->actingAs(new FakeAuthenticatable(id: 7))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('Welcome');
});
```

`actingAs()` takes any `AuthenticatableInterface`, so a test that doesn't need a stored user can pass `FakeAuthenticatable`. See [HTTP Tests](/docs/packages/testing/#http-tests).

### Factories

In Laravel, a model factory returns an array of attributes, usually filled by Faker, and the model creates it:

```php title="database/factories/PostFactory.php"
<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class PostFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(),
            'body' => fake()->paragraph(),
        ];
    }
}
```

```php
$post = Post::factory()->create(['title' => 'Hello']);
```

In Marko, a factory extends `EntityFactory`, names the repository that saves its entities, and returns a constructed entity from `definition()`. There is no Faker dependency:

```php title="tests/Factory/PostFactory.php"
<?php

declare(strict_types=1);

namespace Tests\Factory;

use App\Blog\Entity\Post;
use App\Blog\Repository\PostRepository;
use Marko\Database\Testing\EntityFactory;

/**
 * @extends EntityFactory<Post>
 */
class PostFactory extends EntityFactory
{
    protected const string REPOSITORY = PostRepository::class;

    protected function definition(): Post
    {
        $post = new Post();
        $post->title = 'A post';
        $post->body = 'Some text';

        return $post;
    }
}
```

Overrides are closures that set properties, instead of an attribute array:

```php
$posts = new PostFactory($this->database->application()->container);

$post = $posts->create(fn (Post $post) => $post->title = 'Hello');
```

`make()` builds an entity without saving it, and `createMany()` and `sequence()` match Laravel's `count()` and `sequence()`. See [Entity Factories](/docs/packages/database/#entity-factories).

### Fakes

In Laravel, `Queue::fake()` swaps the facade, and the facade then asserts what was pushed:

```php title="tests/Feature/RegisterTest.php"
<?php

declare(strict_types=1);

use App\Jobs\SendWelcomeEmail;
use Illuminate\Support\Facades\Queue;

it('queues the welcome email', function () {
    Queue::fake();

    $this->post('/register', ['email' => 'ada@example.com']);

    Queue::assertPushed(SendWelcomeEmail::class);
});
```

Marko has no facades to swap. Construct the fake, bind it in the container for the interface it replaces, and assert on the fake itself:

```php title="tests/Feature/RegisterTest.php"
<?php

declare(strict_types=1);

use App\Blog\Job\SendWelcomeEmail;
use Marko\Queue\QueueInterface;
use Marko\Testing\Fake\FakeQueue;

it('queues the welcome email', function () {
    $queue = new FakeQueue();
    $this->database->application()->container->instance(QueueInterface::class, $queue);

    $this->http->post('/register', ['email' => 'ada@example.com']);

    $queue->assertPushed(SendWelcomeEmail::class);
});
```

Fakes are never bound for you. In a unit test, skip the container and pass the fake straight to the class under test. `marko/testing` ships fakes for the mailer, queue, event dispatcher, HTTP client, clock, filesystem, logger and more, and Pest expectations such as `toHaveSent()` and `toHavePushed()`. See [Testing](/docs/guides/testing/) and the [Testing package](/docs/packages/testing/).

## Related Links

- [Introduction](/docs/getting-started/introduction/)
- [Dependency Injection](/docs/concepts/dependency-injection/)
- [Modularity](/docs/concepts/modularity/)
- [Preferences](/docs/concepts/preferences/)
- [Plugins](/docs/concepts/plugins/)
- [Events](/docs/concepts/events/)
- [Routing](/docs/guides/routing/)
- [Database](/docs/guides/database/)
- [Validation](/docs/guides/validation/)
- [Testing](/docs/guides/testing/)
- [Coming From Magento](/docs/guides/coming-from-magento/)
