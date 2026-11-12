---
title: Coming From Magento
description: Map Magento 2 concepts — di.xml, plugins, observers, layout XML and more — to their Marko equivalents.
---

This page is for Magento 2 developers. Marko borrows Magento's extensibility primitives (preferences, plugins, observers, constructor argument injection) but expresses them in PHP attributes and `module.php` instead of XML. Some Magento features are deliberately left out, and where Marko behaves differently from what you expect, this page says so.

Each section shows the Magento code first, then the Marko code that does the same job.

## Quick Reference

| Magento | Marko | Details |
|---|---|---|
| `di.xml` preference | `bindings` or `#[Preference]` | [Preferences](#preferences) |
| `di.xml` type arguments | `arguments` in `module.php` | [Constructor Arguments](#constructor-arguments) |
| Virtual types | Not supported | [Virtual Types](#virtual-types) |
| Plugins | `#[Plugin]` with `#[Before]` / `#[After]` | [Plugins](#plugins) |
| `events.xml` observers | `#[Observer]` on a typed event class | [Events and Observers](#events-and-observers) |
| `module.xml` sequence | Module sequence | [Module Sequence](#module-sequence) |
| Module enable/disable | Enabling and disabling modules | [Enabling and Disabling Modules](#enabling-and-disabling-modules) |
| `registration.php` and module locations | Module locations | [Registration and Module Locations](#registration-and-module-locations) |
| `routes.xml` and controllers | Route attributes on controllers | [Routes and Controllers](#routes-and-controllers) |
| `config.xml`, `env.php`, scope config, `system.xml` | Config files | [Configuration](#configuration) |
| `db_schema.xml` | Entity attributes and migrations | [Database Schema](#database-schema) |
| Extension attributes, columns on another module's table | Adding columns to another module's table | [Adding Columns to Another Module's Table](#adding-columns-to-another-modules-table) |
| Layout XML, blocks, templates | Layouts and templates | [Layout XML, Blocks and Templates](#layout-xml-blocks-and-templates) |
| `crontab.xml` | Scheduled tasks | [Cron](#cron) |
| Message queues | Queue package | [Message Queues](#message-queues) |
| CLI commands | Command attributes | [CLI Commands](#cli-commands) |
| `generated/` and `setup:di:compile` | Generated code | [Generated Code](#generated-code) |

## Preferences

In Magento, `di.xml` maps a type to another with a preference, whether the type is an interface or a class:

```xml title="etc/di.xml"
<preference for="Vendor\Blog\Api\PostRepositoryInterface"
            type="Vendor\Blog\Model\PostRepository"/>
<preference for="Acme\Blog\Controller\PostController"
            type="Vendor\Blog\Controller\CustomPostController"/>
```

```php title="Controller/CustomPostController.php"
<?php

declare(strict_types=1);

namespace Vendor\Blog\Controller;

use Acme\Blog\Controller\PostController;

class CustomPostController extends PostController
{
}
```

Marko splits this in two. To choose the implementation of an interface, add it to `bindings` in `module.php`:

```php title="module.php"
<?php

declare(strict_types=1);

use App\Blog\Repository\PostRepository;
use App\Blog\Repository\PostRepositoryInterface;

return [
    'bindings' => [
        PostRepositoryInterface::class => PostRepository::class,
    ],
];
```

To replace one concrete class with another, put `#[Preference]` on a class that extends the original:

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

There is no XML registration; the attribute is discovered automatically.

Higher-priority modules win: `app/` beats `modules/`, which beats `vendor/`. A static `bindings` entry in an `app/` module overrides a `vendor/` module's binding for the same interface.

Where Marko differs from Magento is ties. In Magento, module sequence silently decides which preference wins. In Marko, two modules at the **same** priority binding the same interface throw `BindingConflictException`, and two same-priority Preferences for one class throw `PreferenceConflictException`. Nothing is resolved by load order.

When you do need to rebind something another module at the same priority already bound, use a `boot` callback. See [Overriding another module's bindings](/docs/concepts/dependency-injection/#overriding-another-modules-bindings).

More detail: [Preferences](/docs/concepts/preferences/) and [Bindings](/docs/concepts/dependency-injection/#bindings).

## Constructor Arguments

In Magento, `di.xml` sets constructor arguments per type:

```xml title="etc/di.xml"
<type name="Vendor\Blog\Notifier\PostNotifier">
    <arguments>
        <argument name="sender" xsi:type="string">noreply@example.com</argument>
        <argument name="slugGenerator" xsi:type="object">Vendor\Blog\Notifier\SlugGenerator</argument>
        <argument name="batchSize" xsi:type="number">50</argument>
    </arguments>
</type>
```

In Marko, the `arguments` key in `module.php` does the same, keyed by class and then parameter name. Parameters you do not list still autowire:

```php title="module.php"
<?php

declare(strict_types=1);

use App\Blog\Notifier\PostNotifier;
use App\Blog\Notifier\SlugGenerator;

return [
    'arguments' => [
        PostNotifier::class => [
            'sender' => 'noreply@example.com',
            'slugGenerator' => SlugGenerator::class,
            'batchSize' => 50,
        ],
    ],
];
```

There is no `xsi:type`: the parameter's declared type decides how the value is read. A class-name string for a class-typed parameter is the equivalent of `xsi:type="object"`. A closure computes the value at resolve time, which covers what Magento's `init_parameter` and `const` types are used for. A Preference inherits the arguments of the class it replaces.

Three differences matter if you are used to `di.xml`:

- **Arguments are keyed by a concrete class, never an interface.** Magento allows `<type name>` on an interface; Marko throws. Bind the interface to a class and key the class.
- **A higher-priority module replaces the whole value of that one argument.** Magento merges `xsi:type="array"` arguments item by item across modules. Marko has no equivalent of that merging.
- **Two same-priority modules setting the same argument throw `ArgumentConflictException`.**

See [Argument Substitution](/docs/concepts/dependency-injection/#argument-substitution) for how values are read, and [Arguments Are Inherited](/docs/concepts/preferences/#arguments-are-inherited) for Preferences.

## Virtual Types

In Magento, a virtual type configures an existing class a second way under a new name that has no file:

```xml title="etc/di.xml"
<virtualType name="Vendor\Blog\Notifier\UrgentPostNotifier"
             type="Vendor\Blog\Notifier\PostNotifier">
    <arguments>
        <argument name="sender" xsi:type="string">urgent@example.com</argument>
    </arguments>
</virtualType>
```

Marko has no virtual types. Write a real subclass and give it its own `arguments`:

```php title="app/blog/src/Notifier/UrgentPostNotifier.php"
<?php

declare(strict_types=1);

namespace App\Blog\Notifier;

class UrgentPostNotifier extends PostNotifier
{
}
```

```php title="module.php"
<?php

declare(strict_types=1);

use App\Blog\Notifier\UrgentPostNotifier;

return [
    'arguments' => [
        UrgentPostNotifier::class => [
            'sender' => 'urgent@example.com',
        ],
    ],
];
```

The subclass can be type-hinted, found in an IDE and analyzed by PHPStan. If you only need to change one configuration of the class, set `arguments` on the class itself and skip the subclass.

See [Why There Are No Virtual Types](/docs/concepts/dependency-injection/#why-there-are-no-virtual-types).

## Plugins

In Magento, a plugin is registered in `di.xml` and uses `before`, `after` and `around` methods:

```xml title="etc/di.xml"
<type name="Vendor\Blog\Api\PostRepositoryInterface">
    <plugin name="post_seo" type="Vendor\Seo\Plugin\PostSeoPlugin" sortOrder="10"/>
</type>
```

```php title="Plugin/PostSeoPlugin.php"
<?php

declare(strict_types=1);

namespace Vendor\Seo\Plugin;

use Vendor\Blog\Api\PostRepositoryInterface;

class PostSeoPlugin
{
    public function beforeGetById(PostRepositoryInterface $subject, int $id): ?array
    {
        return null;
    }

    public function afterSave(PostRepositoryInterface $subject, object $result, object $post): object
    {
        return $result;
    }
}
```

In Marko, a `#[Plugin]` class names its target, and `#[Before]` / `#[After]` mark the methods. There is no registration; plugins are discovered from the attribute. Sort order goes on the method attribute:

```php title="app/seo/src/Plugin/PostSeoPlugin.php"
<?php

declare(strict_types=1);

namespace App\Seo\Plugin;

use App\Blog\Repository\PostRepositoryInterface;
use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: PostRepositoryInterface::class)]
class PostSeoPlugin
{
    #[Before(sortOrder: 10)]
    public function find(int $id): null|array
    {
        return null;
    }

    #[After(sortOrder: 10)]
    public function save(object $result, object $post): object
    {
        return $result;
    }
}
```

What differs from Magento:

- **Methods are named after the target method.** The plugin method is `save`, not `afterSave`. Use `#[Before(method: 'save')]` to hook a method under a different name.
- **There is no `$subject` parameter.** A before plugin takes the target's arguments. An after plugin takes `$result` first, then the arguments. If a plugin needs to call other methods on the target, inject the target's interface through the plugin's constructor.
- **There are no around plugins.** A before plugin returns `null` to pass through, an `array` to replace the arguments, or any other non-null value to short-circuit the original method. That covers part of what around plugins were used for. To replace behavior outright, use a [Preference](#preferences).
- **Equal sort orders are an error.** Two plugins on the same method and timing with the same `sortOrder` make boot fail with `PluginException`. Magento tolerates ties.
- **Readonly and final classes cannot be targeted directly.** Target an interface they implement instead. Marko's own services are often `readonly`.
- **You cannot disable another module's plugin.** There is no equivalent of `disabled="true"`.

See [Plugins](/docs/concepts/plugins/).

## Events and Observers

In Magento, an event is a string name. `events.xml` binds an observer to it, the observer implements `ObserverInterface`, and the event data is an untyped array:

```xml title="etc/events.xml"
<event name="blog_post_created">
    <observer name="track_post_creation" instance="Vendor\Analytics\Observer\TrackPostCreation"/>
</event>
```

```php title="Observer/TrackPostCreation.php"
<?php

declare(strict_types=1);

namespace Vendor\Analytics\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class TrackPostCreation implements ObserverInterface
{
    public function execute(Observer $observer): void
    {
        $post = $observer->getEvent()->getData('post');
        // ...
    }
}
```

Any class that injects `Magento\Framework\Event\ManagerInterface` through its constructor, here as `$eventManager`, dispatches the event:

```php
$this->eventManager->dispatch('blog_post_created', ['post' => $post]);
```

In Marko, an event is a class that extends `Marko\Core\Event\Event`. It takes the place of the string name, and typed constructor properties and getters replace the data array:

```php title="app/blog/src/Events/PostCreated.php"
<?php

declare(strict_types=1);

namespace App\Blog\Events;

use App\Blog\Entity\PostInterface;
use Marko\Core\Event\Event;

class PostCreated extends Event
{
    public function __construct(
        private readonly PostInterface $post,
    ) {}

    public function getPost(): PostInterface
    {
        return $this->post;
    }
}
```

An observer is a class with `#[Observer]` and a typed `handle()` method. There is no `events.xml`; observers are discovered from the attribute:

```php title="app/analytics/src/Observer/TrackPostCreation.php"
<?php

declare(strict_types=1);

namespace App\Analytics\Observer;

use App\Blog\Events\PostCreated;
use Marko\Core\Attributes\Observer;

#[Observer(event: PostCreated::class, priority: 10)]
class TrackPostCreation
{
    public function handle(PostCreated $event): void
    {
        $post = $event->getPost();
        // ...
    }
}
```

Any class that injects `Marko\Core\Event\EventDispatcherInterface` through its constructor, here as `$eventDispatcher`, dispatches the event. It accepts only an `Event` instance; there are no string-named events:

```php
$this->eventDispatcher->dispatch(new PostCreated($post));
```

A higher `priority` runs first. Pass `async: true` to push the observer onto the queue instead of running it inline; that needs `marko/queue` and a running `marko queue:work`. As with plugins, you cannot disable another module's observer.

See [Events](/docs/concepts/events/).

## Module Sequence

In Magento, `module.xml` lists the modules that must load first:

```xml title="etc/module.xml"
<module name="Vendor_Blog">
    <sequence>
        <module name="Magento_Catalog"/>
    </sequence>
</module>
```

In Marko, module names are Composer package names (`acme/blog`), not `Vendor_Module`. A hard dependency goes in `composer.json` `require`, and Marko derives the load order from it automatically. A module that `require`s another always loads after it.

Use the `sequence` key in `module.php` only for a soft ordering hint where there is no `require` relationship:

```php title="module.php"
<?php

declare(strict_types=1);

return [
    'sequence' => [
        'after' => ['acme/blog'],
        'before' => [],
    ],
];
```

Circular dependencies fail loudly at boot instead of being resolved silently.

See [Override timing and load order](/docs/concepts/dependency-injection/#override-timing-and-load-order) and [Module Composition](/docs/concepts/modularity/#module-composition).

## Enabling and Disabling Modules

In Magento, `app/etc/config.php` holds every module's state, and `bin/magento module:enable` and `module:disable` change it:

```php title="app/etc/config.php"
<?php

return [
    'modules' => [
        'Vendor_Blog' => 0,
    ],
];
```

In Marko, `enabled` is a key in **that module's own** `module.php`, and it defaults to `true`:

```php title="module.php"
<?php

declare(strict_types=1);

return [
    'enabled' => false,
];
```

The limits are worth knowing:

- **There is no central file** that toggles other modules, and no `module:enable` or `module:disable` command.
- **A vendor package you do not want is removed** with `composer remove`, not disabled.
- **An enabled module that requires a disabled one** fails boot with `MissingDependencyException`.

`marko module:list` shows each module and whether it is enabled, which is Magento's `module:status`.

See [Creating Modules](/docs/packages/core/#creating-modules).

## Registration and Module Locations

In Magento, every module ships a `registration.php`, and lives in `app/code/Vendor/Module` or in `vendor`:

```php title="registration.php"
<?php

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(ComponentRegistrar::MODULE, 'Vendor_Blog', __DIR__);
```

Marko has no registration file. A package is a module when its `composer.json` declares the module flag:

```json title="composer.json"
{
    "name": "acme/blog",
    "extra": {
        "marko": {
            "module": true
        }
    }
}
```

Without the flag, the package is a plain Composer dependency and Marko ignores it. The flag is the real counterpart of `registration.php`.

Modules are discovered in three places:

- `vendor/` --- two levels deep (`vendor/acme/blog`)
- `modules/` --- any depth
- `app/` --- one level (`app/blog/`, not `app/code/Vendor/Module/`)

Priority is `app/` over `modules/` over `vendor/`.

See [Module Discovery](/docs/concepts/modularity/#module-discovery) and [Project Structure](/docs/getting-started/project-structure/).

## Routes and Controllers

In Magento, `routes.xml` declares a frontName, and each action is its own class at `Controller/Path/Action.php` with an `execute()` method. The URL `/blog/post/view/id/5` maps to it:

```xml title="etc/frontend/routes.xml"
<router id="standard">
    <route id="blog" frontName="blog">
        <module name="Vendor_Blog"/>
    </route>
</router>
```

```php title="Controller/Post/View.php"
<?php

declare(strict_types=1);

namespace Vendor\Blog\Controller\Post;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;

class View implements HttpGetActionInterface
{
    public function __construct(
        private RequestInterface $request,
        private RawFactory $rawFactory,
    ) {}

    public function execute(): Raw
    {
        $id = (int) $this->request->getParam('id');

        return $this->rawFactory->create()->setContents('Post ' . $id);
    }
}
```

In Marko, there is no `routes.xml`. The route is an attribute on the controller method, route parameters go in the path, and they arrive as typed method arguments:

```php title="app/blog/src/Controller/PostController.php"
<?php

declare(strict_types=1);

namespace App\Blog\Controller;

use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;

class PostController
{
    #[Get('/blog/post/{id}')]
    public function show(int $id): Response
    {
        return new Response('Post ' . $id);
    }
}
```

One controller can hold as many routes as you like, each with its own `#[Get]`, `#[Post]` or other method attribute, and `#[Middleware]` attaches middleware per method.

To replace a vendor controller, use `#[Preference]` as in [Preferences](#preferences). To remove one inherited route from the replacement, put `#[DisableRoute]` on the method. Two controllers claiming the same route fail loudly at boot.

See [Routing](/docs/guides/routing/) and [Overriding Vendor Routes](/docs/packages/routing/#overriding-vendor-routes).

## Configuration

Magento layers configuration from `config.xml` defaults, `env.php`, the admin UI and `ScopeConfigInterface`. Marko is code and environment variables only. `system.xml` and the admin settings UI have **no equivalent**.

### Defaults

In Magento, a module's `config.xml` sets default values, and store-scoped values come from the admin and `core_config_data`:

```xml title="etc/config.xml"
<config>
    <default>
        <blog>
            <general>
                <per_page>10</per_page>
            </general>
        </blog>
    </default>
</config>
```

In Marko, each module has `config/*.php` files that return arrays. Scoped values sit in the same file under `scopes`. Files merge in module priority order, and the **project-root `config/` directory** is applied last and wins:

```php title="app/blog/config/blog.php"
<?php

declare(strict_types=1);

return [
    'default' => [
        'per_page' => 10,
    ],
    'scopes' => [
        'tenant-eu' => [
            'per_page' => 20,
        ],
    ],
];
```

### Environment

In Magento, `env.php` holds per-environment values such as database credentials:

```php title="app/etc/env.php"
<?php

return [
    'db' => [
        'connection' => [
            'default' => [
                'host' => 'localhost',
            ],
        ],
    ],
];
```

In Marko, per-environment values go in `.env` at the project root. Real environment variables take precedence over it:

```bash title=".env"
DB_HOST=localhost
DB_PORT=5432
```

A config file reads them through `Marko\Config\Env`, with typed parsing and a default. Application code never reads environment variables directly:

```php title="config/database.php"
<?php

declare(strict_types=1);

use Marko\Config\Env;

return [
    'host' => Env::string('DB_HOST', 'localhost'),
    'port' => Env::int('DB_PORT', 5432),
];
```

### Reading Values

In Magento, a class injects `ScopeConfigInterface` and reads a path for a scope. The value comes back untyped:

```php title="Model/PostLimits.php"
<?php

declare(strict_types=1);

namespace Vendor\Blog\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class PostLimits
{
    public function __construct(
        private ScopeConfigInterface $scopeConfig,
    ) {}

    public function perPage(string $storeCode): int
    {
        return (int) $this->scopeConfig->getValue('blog/general/per_page', ScopeInterface::SCOPE_STORE, $storeCode);
    }
}
```

In Marko, a class injects `ConfigRepositoryInterface` and calls a typed accessor. There are no default fallbacks; a missing key throws:

```php title="app/blog/src/Service/PostLimits.php"
<?php

declare(strict_types=1);

namespace App\Blog\Service;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigNotFoundException;

class PostLimits
{
    public function __construct(
        private ConfigRepositoryInterface $configRepository,
    ) {}

    /**
     * @throws ConfigNotFoundException
     */
    public function perPage(string $tenant): int
    {
        return $this->configRepository->getInt('blog.per_page', scope: $tenant);
    }
}
```

Pass `scope:` per call, or call `withScope()` to get a repository bound to one scope. The scope cascade is **one level**: `scopes.{scope}`, then `default`, then the direct key. It is not Magento's default, website, store view hierarchy.

See [Merge Priority](/docs/packages/config/#merge-priority), [Environment Variables](/docs/getting-started/configuration/#environment-variables) and [Scoped Configuration](/docs/packages/config/#scoped-configuration-multi-tenant).

## Database Schema

In Magento, `db_schema.xml` declares tables, and a whitelist file tracks what the declarative schema may drop:

```xml title="etc/db_schema.xml"
<schema>
    <table name="posts">
        <column xsi:type="int" name="id" unsigned="true" nullable="false" identity="true"/>
        <column xsi:type="varchar" name="title" length="255" nullable="false" default=""/>
        <constraint xsi:type="primary" referenceId="PRIMARY">
            <column name="id"/>
        </constraint>
    </table>
</schema>
```

A model class holds a row's data:

```php title="Model/Post.php"
<?php

declare(strict_types=1);

namespace Vendor\Blog\Model;

use Magento\Framework\Model\AbstractModel;

class Post extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(ResourceModel\Post::class);
    }

    public function getTitle(): string
    {
        return (string) $this->getData('title');
    }
}
```

In Marko, entity classes are the schema and the data object. `#[Table]` and `#[Column]` attributes describe the table, so one class does the job of `db_schema.xml` and the model, and there is no whitelist:

```php title="app/blog/src/Entity/Post.php"
<?php

declare(strict_types=1);

namespace App\Blog\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(name: 'posts')]
class Post extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column]
    public string $title = '';
}
```

`marko db:migrate` generates migrations from entity changes in development, and in other environments only applies pending migration files. `marko db:diff` previews the difference between entities and the database. Reads and writes go through Data Mapper repositories rather than Magento's active-record models and resource models.

See [Database](/docs/guides/database/) and [Entity-Driven Schema](/docs/packages/database/#entity-driven-schema).

## Adding Columns to Another Module's Table

In Magento, a second module's `db_schema.xml` adds columns to a table another module owns:

```xml title="etc/db_schema.xml"
<schema>
    <table name="customer_entity">
        <column xsi:type="varchar" name="plan" length="255" nullable="true"/>
    </table>
</schema>
```

In Marko, declare a companion entity with `#[Table(extends: ...)]`:

```php title="app/billing/src/Entity/UserBilling.php"
<?php

declare(strict_types=1);

namespace App\Billing\Entity;

use Acme\Auth\Entity\User;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table(extends: User::class)]
class UserBilling extends Entity
{
    #[Column]
    public ?string $plan = null;
}
```

The framework merges its columns into the parent's table, and you read them back with `$user->companion(UserBilling::class)`.

The limits:

- It only adds columns to the parent's **own** table.
- Chained extension is not supported: an extender cannot itself be extended.
- Extenders have no repository of their own; use the parent's.

Magento extension attributes backed by another table or a join, and `extension_attributes.xml`, have **no equivalent**. Model that data as a separate entity with its own repository.

See [Table Extension](/docs/packages/database/#table-extension).

## Layout XML, Blocks and Templates

In Magento, layout handle XML places blocks, `Block` classes supply data, and `.phtml` files render it:

```xml title="view/frontend/layout/catalog_product_view.xml"
<page>
    <body>
        <referenceContainer name="content">
            <block class="Vendor\Shipping\Block\Estimate"
                   name="shipping.estimate"
                   template="Vendor_Shipping::estimate.phtml"/>
        </referenceContainer>
    </body>
</page>
```

```php title="Block/Estimate.php"
<?php

declare(strict_types=1);

namespace Vendor\Shipping\Block;

use Magento\Framework\View\Element\Template;

class Estimate extends Template
{
    public function getProductId(): int
    {
        return (int) $this->getRequest()->getParam('id');
    }
}
```

In Marko, `marko/layout` replaces the XML. A `#[Component]` class declares a template, the handle it appears on and a slot, so one class does the job of the layout XML entry and the block. `#[Layout]` on a controller picks the page layout. Ordering uses `sortOrder`, `before` and `after` where Magento used `<move>`. Components from other modules can inject themselves into any page.

```php title="app/shipping/src/Component/ShippingEstimate.php"
<?php

declare(strict_types=1);

namespace App\Shipping\Component;

use Marko\Layout\Attributes\Component;

#[Component(
    template: 'shipping::components/estimate.html',
    handle: 'products_product_show',
    slot: 'content',
)]
class ShippingEstimate
{
    public function data(int $id): array
    {
        return ['productId' => $id];
    }
}
```

Templates render through `marko/view` and whichever template engine driver you install; the [View package](/docs/packages/view/) lists them. None of them renders plain PHP templates, so there is no `.phtml` equivalent.

Magento's `<referenceBlock remove="true">` becomes an `#[After]` plugin on `ComponentCollectorInterface::collect()`. See [Cross-Module Extensibility](/docs/guides/layout/#cross-module-extensibility).

See [Layouts](/docs/guides/layout/), [Layout package](/docs/packages/layout/) and [View package](/docs/packages/view/).

## Cron

In Magento, `crontab.xml` registers jobs in cron groups, each job points at a class method, and `bin/magento cron:run` executes them:

```xml title="etc/crontab.xml"
<group id="default">
    <job name="blog_nightly_cleanup" instance="Vendor\Blog\Cron\Cleanup" method="execute">
        <schedule>0 0 * * *</schedule>
    </job>
</group>
```

```php title="Cron/Cleanup.php"
<?php

declare(strict_types=1);

namespace Vendor\Blog\Cron;

class Cleanup
{
    public function execute(): void
    {
        // ...
    }
}
```

In Marko, there is no `crontab.xml`. Inject `Schedule` from `marko/scheduler` into a `module.php` `boot` callback, along with any service the task calls. The cleanup class is a plain class like Magento's:

```php title="module.php"
<?php

declare(strict_types=1);

use App\Blog\Cron\Cleanup;
use Marko\Scheduler\Schedule;

return [
    'boot' => function (Schedule $schedule, Cleanup $cleanup): void {
        $schedule->call(fn () => $cleanup->execute())->daily()->description('Nightly cleanup');
    },
];
```

`$schedule->command('blog:prune')` schedules a CLI command instead of a closure. One system cron entry runs `marko schedule:run` every minute, and the scheduler decides what is due.

Cron groups have **no equivalent**. The nearest per-task options are `withoutOverlapping()` and `runInBackground()`.

See [Scheduling](/docs/guides/scheduling/).

## Message Queues

In Magento, a queue needs `communication.xml`, `queue_topology.xml`, `queue_consumer.xml` and `queue_publisher.xml`, a handler class, and a publisher call. `queue:consumers:start` runs the consumer. The topic and consumer files look like this, and the topology and publisher files are similar:

```xml title="etc/communication.xml"
<config>
    <topic name="blog.welcome_email" request="int"/>
</config>
```

```xml title="etc/queue_consumer.xml"
<config>
    <consumer name="blog.welcome_email" queue="blog.welcome_email"
              handler="Vendor\Blog\Model\SendWelcomeEmail::process"/>
</config>
```

```php title="Model/SendWelcomeEmail.php"
<?php

declare(strict_types=1);

namespace Vendor\Blog\Model;

class SendWelcomeEmail
{
    public function process(int $userId): void
    {
        // ...
    }
}
```

Any class that injects `Magento\Framework\MessageQueue\PublisherInterface` through its constructor, here as `$publisher`, publishes the message:

```php
$this->publisher->publish('blog.welcome_email', $userId);
```

In Marko, a job class extends `Marko\Queue\Job` and implements `handle()`. It is both the message and its handler, so there is no topic, consumer, topology or publisher configuration:

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

Any class that injects `Marko\Queue\QueueInterface` through its constructor, here as `$queue`, pushes the job, and `marko queue:work` processes it:

```php
$this->queue->push(new SendWelcomeEmail($userId));
```

A driver package supplies the backend (sync, database, Redis or RabbitMQ). For event-driven background work, use an async observer.

See [Queues](/docs/guides/queues/) and [Queue package](/docs/packages/queue/).

## CLI Commands

In Magento, a command extends Symfony Console's `Command` and is registered through the `CommandList` argument in `di.xml`:

```xml title="etc/di.xml"
<type name="Magento\Framework\Console\CommandList">
    <arguments>
        <argument name="commands" xsi:type="array">
            <item name="blog_prune" xsi:type="object">Vendor\Blog\Console\Command\PruneCommand</item>
        </argument>
    </arguments>
</type>
```

```php title="Console/Command/PruneCommand.php"
<?php

declare(strict_types=1);

namespace Vendor\Blog\Console\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PruneCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('blog:prune')->setDescription('Delete old drafts');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Pruned.');

        return Command::SUCCESS;
    }
}
```

In Marko, there is no registration. Put `#[Command]` on a class that implements `CommandInterface`; the attribute carries the name and description. It is discovered automatically, and you run it with `marko blog:prune`. The `Input` and `Output` types are Marko's own, not Symfony Console's:

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
    public function execute(Input $input, Output $output): int
    {
        $output->writeLine('Pruned.');

        return 0;
    }
}
```

See [Registering Console Commands](/docs/packages/core/#registering-console-commands) and [CLI package](/docs/packages/cli/).

## Generated Code

In Magento, `setup:di:compile` writes factories, proxies and interceptors into `generated/`.

Marko needs no compile step to run, and writes **no generated PHP classes to disk**. Plugin interceptors are generated in memory with `eval`. See [What gets generated](/docs/concepts/plugins/#what-gets-generated).

Some files are still written:

- `marko discovery:cache` writes `storage/cache/discovery.php`. See [Discovery Cache](/docs/packages/core/#discovery-cache).
- Template engines compile templates to disk. See [Precompiling Templates](/docs/packages/view/#precompiling-templates).

Factories have **no equivalent**: inject services, and use `new` for entities and value objects. Proxies (lazy loading) have **no equivalent** either.

## Related Links

- [Modularity](/docs/concepts/modularity/)
- [Dependency Injection](/docs/concepts/dependency-injection/)
- [Preferences](/docs/concepts/preferences/)
- [Plugins](/docs/concepts/plugins/)
- [Events](/docs/concepts/events/)
- [Routing](/docs/guides/routing/)
- [Database](/docs/guides/database/)
- [Layouts](/docs/guides/layout/)
- [Scheduling](/docs/guides/scheduling/)
- [Queues](/docs/guides/queues/)
- [Configuration](/docs/getting-started/configuration/)
