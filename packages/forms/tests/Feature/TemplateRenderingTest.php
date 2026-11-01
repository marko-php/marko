<?php

declare(strict_types=1);

use Latte\Engine;
use Marko\Core\Container\Container;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Discovery\CachedDiscovery;
use Marko\Core\Module\ModuleRepository;
use Marko\Core\Module\ModuleRepositoryInterface;
use Marko\Forms\Tests\Fixtures\FixedCsrfTokenManager;
use Marko\Forms\Tests\Fixtures\FormsHarness;
use Marko\Forms\View\FormTemplateFunctionProvider;
use Marko\Forms\View\FormViewDataProvider;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Validation\Validation\ValidationErrors;
use Marko\View\Discovery\ViewProviderCacheContributor;
use Marko\View\Latte\Extensions\TemplateFunctionExtension as LatteTemplateFunctionExtension;
use Marko\View\Latte\LatteView;
use Marko\View\TemplateResolverInterface;
use Marko\View\Twig\TemplateFunctionExtension as TwigTemplateFunctionExtension;
use Marko\View\Twig\TwigView;
use Marko\View\ViewDataMerger;
use Marko\View\ViewInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * The container a cached boot builds once marko/forms' providers are discovered.
 */
function formsViewContainer(
    FlashedFormStateInterface $formState,
): Container {
    $container = new Container();
    $container->instance(ContainerInterface::class, $container);
    $container->instance(ModuleRepositoryInterface::class, new ModuleRepository([]));
    $container->instance(CachedDiscovery::class, new CachedDiscovery([
        ViewProviderCacheContributor::KEY => [
            'template_functions' => [FormTemplateFunctionProvider::class],
            'view_data' => [FormViewDataProvider::class],
        ],
    ]));
    $container->instance(CsrfTokenManagerInterface::class, new FixedCsrfTokenManager());
    $container->instance(FlashedFormStateInterface::class, $formState);

    return $container;
}

function formsTemplateResolver(
    string $template,
    string $extension,
): TemplateResolverInterface {
    $path = sys_get_temp_dir() . '/marko-forms-' . bin2hex(random_bytes(8)) . ".$extension";
    file_put_contents($path, $template);

    return new readonly class ($path) implements TemplateResolverInterface
    {
        public function __construct(
            private string $path,
        ) {}

        public function resolve(string $template): string
        {
            return $this->path;
        }

        public function getSearchedPaths(string $template): array
        {
            return [$this->path];
        }

        public function all(): array
        {
            return [];
        }
    };
}

/**
 * @param array<string, string> $templates engine => template source
 * @return array<string, ViewInterface>
 */
function formsViews(
    FlashedFormStateInterface $formState,
    array $templates,
): array {
    $container = formsViewContainer($formState);

    $latte = new Engine();
    $latte->addExtension($container->get(LatteTemplateFunctionExtension::class));

    $twig = new Environment(new ArrayLoader(), ['autoescape' => 'html']);
    $twig->addExtension($container->get(TwigTemplateFunctionExtension::class));

    return [
        'latte' => new LatteView(
            $latte,
            formsTemplateResolver($templates['latte'], 'latte'),
            $container->get(ViewDataMerger::class),
        ),
        'twig' => new TwigView(
            $twig,
            formsTemplateResolver($templates['twig'], 'twig'),
            $container->get(ViewDataMerger::class),
        ),
    ];
}

function flashedFormsHarness(): FormsHarness
{
    $harness = new FormsHarness();
    $harness->writer->flash(
        new ValidationErrors(['email' => ['The email is <invalid>.']]),
        ['email' => '<script>x</script>', 'address' => ['city' => 'Lisbon & Porto']],
    );
    $harness->nextRequest();

    return $harness;
}

describe('marko/forms template helpers', function (): void {
    it('renders csrf_field() unescaped with the token escaped inside it', function (string $engine): void {
        $views = formsViews(new FormsHarness()->state, [
            'latte' => '{csrf_field()}',
            'twig' => '{{ csrf_field() }}',
        ]);

        expect($views[$engine]->renderToString('forms::page'))
            ->toBe('<input type="hidden" name="_token" value="tok&quot;&lt;&amp;&gt;en">');
    })->with(['latte', 'twig']);

    it('renders csrf_token() escaped', function (string $engine): void {
        $views = formsViews(new FormsHarness()->state, [
            'latte' => '{csrf_token()}',
            'twig' => '{{ csrf_token() }}',
        ]);

        // Latte leaves '"' as is in a text context; both engines escape '<', '&' and '>'
        expect($views[$engine]->renderToString('forms::page'))->toContain('&lt;&amp;&gt;en')
            ->not->toContain('<');
    })->with(['latte', 'twig']);

    it('renders old() escaped, by dot path and with a default', function (string $engine): void {
        $views = formsViews(flashedFormsHarness()->state, [
            'latte' => '{old("email")}|{old("address.city")}|{old("name", "Ann")}',
            'twig' => '{{ old("email") }}|{{ old("address.city") }}|{{ old("name", "Ann") }}',
        ]);

        expect($views[$engine]->renderToString('forms::page'))
            ->toBe('&lt;script&gt;x&lt;/script&gt;|Lisbon &amp; Porto|Ann');
    })->with(['latte', 'twig']);

    it('renders errors() as the flashed ValidationErrors', function (string $engine): void {
        $views = formsViews(flashedFormsHarness()->state, [
            'latte' => '{errors()->first("email")}|{errors()->count()}',
            'twig' => '{{ errors().first("email") }}|{{ errors().count() }}',
        ]);

        expect($views[$engine]->renderToString('forms::page'))->toBe('The email is &lt;invalid&gt;.|1');
    })->with(['latte', 'twig']);

    it('shares $errors and $old with every template', function (string $engine): void {
        $views = formsViews(flashedFormsHarness()->state, [
            'latte' => '{if $errors->has("email")}{$errors->first("email")}{/if}|{$old["email"]}|{$old["address.city"]}|{$old["missing"]}',
            'twig' => '{% if errors.has("email") %}{{ errors.first("email") }}{% endif %}|{{ old.email }}|{{ old.address.city }}|{{ old.missing }}',
        ]);

        expect($views[$engine]->renderToString('forms::page'))
            ->toBe('The email is &lt;invalid&gt;.|&lt;script&gt;x&lt;/script&gt;|Lisbon &amp; Porto|');
    })->with(['latte', 'twig']);

    it('shares empty $errors and $old when nothing was flashed', function (string $engine): void {
        $views = formsViews(new FormsHarness()->state, [
            'latte' => '{if $errors->isEmpty()}none{/if}|{$old["email"]}',
            'twig' => '{% if errors.isEmpty() %}none{% endif %}|{{ old.email }}',
        ]);

        expect($views[$engine]->renderToString('forms::page'))->toBe('none|');
    })->with(['latte', 'twig']);
});
