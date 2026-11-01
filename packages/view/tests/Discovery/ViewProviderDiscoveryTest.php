<?php

declare(strict_types=1);

use Marko\Core\Module\ModuleManifest;
use Marko\View\Discovery\ViewProviderDiscovery;
use Marko\View\Exceptions\ViewProviderException;
use Marko\View\Functions\RouteFunctionProvider;

/**
 * A module whose src/ holds the given files (relative path => PHP source).
 *
 * @param array<string, string> $files
 */
function viewProviderDiscoveryModule(
    array $files,
): ModuleManifest {
    $path = sys_get_temp_dir() . '/marko-view-provider-discovery-' . bin2hex(random_bytes(8));

    foreach ($files as $relativePath => $source) {
        $file = $path . '/src/' . $relativePath;
        is_dir(dirname($file)) || mkdir(dirname($file), 0755, true);
        file_put_contents($file, $source);
    }

    return new ModuleManifest(name: 'test/providers', version: '1.0.0', path: $path);
}

describe('ViewProviderDiscovery', function (): void {
    it('finds template function and view data providers by attribute', function (): void {
        $suffix = bin2hex(random_bytes(4));
        $namespace = "ViewProviderDiscoveryTest$suffix";
        $module = viewProviderDiscoveryModule([
            'Functions.php' => <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\View\Attributes\TemplateFunctions;
use Marko\View\TemplateFunctionProviderInterface;

#[TemplateFunctions]
class Functions implements TemplateFunctionProviderInterface
{
    public function functions(): array { return []; }
}
PHP,
            'Data/Shared.php' => <<<PHP
<?php

declare(strict_types=1);

namespace $namespace\\Data;

use Marko\View\Attributes\ViewData;
use Marko\View\ViewDataProviderInterface;

#[ViewData]
class Shared implements ViewDataProviderInterface
{
    public function data(): array { return []; }
}
PHP,
            'Plain.php' => <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\View\TemplateFunctionProviderInterface;

// Implements the interface but carries no #[TemplateFunctions] attribute.
class Plain implements TemplateFunctionProviderInterface
{
    public function functions(): array { return []; }
}
PHP,
        ]);

        $classes = new ViewProviderDiscovery()->discover([$module]);

        expect($classes->templateFunctionProviders)->toBe(["$namespace\\Functions"])
            ->and($classes->viewDataProviders)->toBe(["$namespace\\Data\\Shared"]);
    });

    it('keeps module order across modules', function (): void {
        $suffix = bin2hex(random_bytes(4));
        $source = fn (string $namespace): string => <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\View\Attributes\ViewData;
use Marko\View\ViewDataProviderInterface;

#[ViewData]
class Provider implements ViewDataProviderInterface
{
    public function data(): array { return []; }
}
PHP;
        $first = viewProviderDiscoveryModule(['Provider.php' => $source("First$suffix")]);
        $second = viewProviderDiscoveryModule(['Provider.php' => $source("Second$suffix")]);

        $classes = new ViewProviderDiscovery()->discover([$second, $first]);

        expect($classes->viewDataProviders)->toBe(["Second$suffix\\Provider", "First$suffix\\Provider"]);
    });

    it('throws when a #[TemplateFunctions] class does not implement the provider interface', function (): void {
        $namespace = 'ViewProviderDiscoveryTest' . bin2hex(random_bytes(4));
        $module = viewProviderDiscoveryModule([
            'Broken.php' => <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\View\Attributes\TemplateFunctions;

#[TemplateFunctions]
class Broken {}
PHP,
        ]);

        new ViewProviderDiscovery()->discover([$module]);
    })->throws(ViewProviderException::class, 'must implement Marko\View\TemplateFunctionProviderInterface');

    it('throws when a #[ViewData] class does not implement the provider interface', function (): void {
        $namespace = 'ViewProviderDiscoveryTest' . bin2hex(random_bytes(4));
        $module = viewProviderDiscoveryModule([
            'Broken.php' => <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

use Marko\View\Attributes\ViewData;

#[ViewData]
class Broken {}
PHP,
        ]);

        new ViewProviderDiscovery()->discover([$module]);
    })->throws(ViewProviderException::class, 'must implement Marko\View\ViewDataProviderInterface');

    it('finds nothing in a module without a src directory', function (): void {
        $module = new ModuleManifest(
            name: 'test/empty',
            version: '1.0.0',
            path: sys_get_temp_dir() . '/marko-view-provider-missing-' . bin2hex(random_bytes(4)),
        );

        $classes = new ViewProviderDiscovery()->discover([$module]);

        expect($classes->templateFunctionProviders)->toBe([])
            ->and($classes->viewDataProviders)->toBe([]);
    });

    it('discovers the route function provider shipped by marko/view', function (): void {
        $module = new ModuleManifest(name: 'marko/view', version: '1.0.0', path: dirname(__DIR__, 2));

        $classes = new ViewProviderDiscovery()->discover([$module]);

        expect($classes->templateFunctionProviders)->toBe([RouteFunctionProvider::class])
            ->and($classes->viewDataProviders)->toBe([]);
    });
});
