<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\View\Discovery\ViewProviderCacheContributor;
use Marko\View\Exceptions\ViewProviderException;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionRegistry;
use Marko\View\Tests\Fixtures\ConflictingFunctionProvider;
use Marko\View\Tests\Fixtures\GreetingFunctionProvider;

/**
 * @param list<class-string> $functionProviders
 */
function templateFunctionRegistry(
    array $functionProviders,
): TemplateFunctionRegistry {
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ModuleRepositoryInterface::class, new ModuleRepository([]));
    $container->instance(CachedDiscovery::class, new CachedDiscovery([
        ViewProviderCacheContributor::KEY => [
            'template_functions' => $functionProviders,
            'view_data' => [],
        ],
    ]));

    return $container->get(TemplateFunctionRegistry::class);
}

describe('TemplateFunctionRegistry', function (): void {
    it('returns every function of every discovered provider', function (): void {
        $functions = templateFunctionRegistry([GreetingFunctionProvider::class])->all();

        expect(array_map(fn (TemplateFunction $function): string => $function->name, $functions))
            ->toBe(['greet', 'badge']);
    });

    it('returns no functions when no provider is discovered', function (): void {
        expect(templateFunctionRegistry([])->all())->toBe([]);
    });

    it('builds the function list once', function (): void {
        $registry = templateFunctionRegistry([GreetingFunctionProvider::class]);

        expect($registry->all()[0])->toBe($registry->all()[0]);
    });

    it('throws naming both providers when two register the same function name', function (): void {
        templateFunctionRegistry([GreetingFunctionProvider::class, ConflictingFunctionProvider::class])->all();
    })->throws(
        ViewProviderException::class,
        "Template function 'greet' is registered by both " . GreetingFunctionProvider::class
            . ' and ' . ConflictingFunctionProvider::class,
    );
});
