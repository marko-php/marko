<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Support;

use Marko\Core\Application;
use Marko\Core\Container\Container;
use Marko\Core\Contracts\ResettableInterface;
use Marko\Core\Exceptions\BindingConflictException;
use Marko\Core\Exceptions\BindingException;
use Marko\Core\Exceptions\CircularDependencyException;
use Marko\Core\Exceptions\CommandException;
use Marko\Core\Exceptions\DiscoveryCacheException;
use Marko\Core\Exceptions\EventException;
use Marko\Core\Exceptions\ModuleException;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Exceptions\PreferenceConflictException;
use Marko\Routing\Exceptions\RouteConflictException;
use Marko\Routing\Exceptions\RouteException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use NoDiscard;
use Psr\Container\ContainerExceptionInterface;
use ReflectionException;
use RuntimeException;

/**
 * Boots one real Marko application against a fixture project directory and
 * drives many {@see Request} objects through it, in process — no PSR-7
 * bridges, no RoadRunner binary, no subprocess.
 *
 * The application boots exactly once for the lifetime of a harness instance.
 * Stateful services (Session, SessionGuard, ...) are deliberately left to
 * persist across handle() calls: that persistence, and the ability to clear
 * it on demand via reset(), is the entire point of this harness.
 */
class InProcessRequestHarness
{
    private ?Application $application = null;

    public function __construct(
        private readonly string $basePath,
    ) {}

    /**
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|RuntimeException|DiscoveryCacheException
     */
    #[NoDiscard]
    public function handle(
        Request $request,
    ): Response {
        return $this->application()->router->handle($request);
    }

    /**
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|RuntimeException|DiscoveryCacheException
     */
    public function container(): Container
    {
        $container = $this->application()->container;

        if (!$container instanceof Container) {
            throw new RuntimeException(
                'Expected the booted application to expose a concrete Container instance.',
            );
        }

        return $container;
    }

    /**
     * Clear request-scoped state from every currently resolved
     * ResettableInterface instance (e.g. Session, SessionGuard). Opt-in and
     * non-destructive — nothing is reset automatically between handle()
     * calls, so leak scenarios remain observable unless a caller resets.
     *
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|RuntimeException|DiscoveryCacheException
     */
    public function reset(): void
    {
        foreach ($this->container()->resolvedInstances(ResettableInterface::class) as $resettable) {
            $resettable->reset();
        }
    }

    /**
     * @throws ModuleException|CircularDependencyException|BindingConflictException|BindingException|PluginException|PreferenceConflictException|EventException|ContainerExceptionInterface|RouteException|RouteConflictException|CommandException|ReflectionException|RuntimeException|DiscoveryCacheException
     */
    private function application(): Application
    {
        return $this->application ??= Application::boot($this->basePath);
    }
}
