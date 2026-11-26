<?php

declare(strict_types=1);

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Core\Attributes\Subject;
use Marko\Core\Exceptions\PluginException;
use Marko\Core\Module\ModuleManifest;
use Marko\Core\Plugin\PluginDefinition;
use Marko\Core\Plugin\PluginDiscovery;

// Helper function for recursive directory cleanup
function cleanupPluginTestDirectory(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    foreach ($items as $item) {
        if (
            $item === '.'
            || $item === '..'
        ) {
            continue;
        }
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            cleanupPluginTestDirectory($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

// Helper to create a test plugin class file
function createPluginClass(
    string $path,
    string $className,
    string $namespace,
    string $targetClass,
    array $beforeMethods = [],
    array $afterMethods = [],
): void {
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $useStatements = "use Marko\\Core\\Attributes\\Plugin;\n";
    if (!empty($beforeMethods)) {
        $useStatements .= "use Marko\\Core\\Attributes\\Before;\n";
    }
    if (!empty($afterMethods)) {
        $useStatements .= "use Marko\\Core\\Attributes\\After;\n";
    }

    $methods = '';
    foreach ($beforeMethods as $method => $sortOrder) {
        $sortOrderParam = $sortOrder !== 0 ? "sortOrder: $sortOrder" : '';
        $methods .= "\n    #[Before($sortOrderParam)]\n    public function $method(): void {}\n";
    }
    foreach ($afterMethods as $method => $sortOrder) {
        $sortOrderParam = $sortOrder !== 0 ? "sortOrder: $sortOrder" : '';
        $methods .= "\n    #[After($sortOrderParam)]\n    public function $method(): void {}\n";
    }

    $content = <<<PHP
<?php

declare(strict_types=1);

namespace $namespace;

$useStatements
#[Plugin(target: $targetClass::class)]
class $className
{
$methods}

PHP;

    file_put_contents($path, $content);
}

it('discovers plugin classes in module src directories', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-plugin-test-' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/src/Plugin', 0755, true);

    // Create a plugin class file
    createPluginClass(
        path: $tempDir . '/src/Plugin/UserServicePlugin.php',
        className: 'UserServicePlugin',
        namespace: 'Acme\Blog\Plugin',
        targetClass: 'Acme\Blog\Services\UserService',
        beforeMethods: ['getUser' => 0],
    );

    // Create composer.json for autoloading
    file_put_contents(
        $tempDir . '/composer.json',
        json_encode([
            'name' => 'acme/blog',
            'autoload' => [
                'psr-4' => ['Acme\\Blog\\' => 'src/'],
            ],
        ]),
    );

    $manifest = new ModuleManifest(
        name: 'acme/blog',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new PluginDiscovery();
    $definitions = $discovery->discoverInModule($manifest);

    expect($definitions)
        ->toBeArray()
        ->toHaveCount(1);

    expect($definitions[0])
        ->toBeInstanceOf(PluginDefinition::class)
        ->and($definitions[0]->pluginClass)->toEndWith('UserServicePlugin');

    cleanupPluginTestDirectory($tempDir);
});

it('discovers plugin classes in module src directories with new naming', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko-plugin-test-' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/src/Plugin', 0755, true);

    // Create a plugin class file using new unprefixed method naming with #[Before] attribute
    createPluginClass(
        path: $tempDir . '/src/Plugin/ProductServicePlugin.php',
        className: 'ProductServicePlugin',
        namespace: 'Acme\Shop\Plugin',
        targetClass: 'Acme\Shop\Services\ProductService',
        beforeMethods: ['getProduct' => 0],
    );

    file_put_contents(
        $tempDir . '/composer.json',
        json_encode([
            'name' => 'acme/shop',
            'autoload' => [
                'psr-4' => ['Acme\\Shop\\' => 'src/'],
            ],
        ]),
    );

    $manifest = new ModuleManifest(
        name: 'acme/shop',
        version: '1.0.0',
        path: $tempDir,
    );

    $discovery = new PluginDiscovery();
    $definitions = $discovery->discoverInModule($manifest);

    expect($definitions)
        ->toBeArray()
        ->toHaveCount(1);

    expect($definitions[0])
        ->toBeInstanceOf(PluginDefinition::class)
        ->and($definitions[0]->pluginClass)->toEndWith('ProductServicePlugin');

    cleanupPluginTestDirectory($tempDir);
});

// Define test fixtures for reflection-based tests
class TargetService
{
    public function doSomething(): string
    {
        return 'result';
    }
}

#[Plugin(target: TargetService::class)]
class TargetServicePlugin
{
    /** @noinspection PhpUnused - Discovered via reflection */
    #[Before(sortOrder: 10)]
    public function doSomething(): void {}

    /** @noinspection PhpUnused - Discovered via reflection */
    #[After(sortOrder: 20)]
    public function doSomethingAfter(): void {}
}

it('extracts target class from Plugin attribute', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition)
        ->toBeInstanceOf(PluginDefinition::class)
        ->and($definition->targetClass)->toBe(TargetService::class);
});

it('extracts target-method-keyed before methods from plugin class', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->beforeMethods)
        ->toBeArray()
        ->toHaveKey('doSomething')
        ->and($definition->beforeMethods['doSomething'])->toHaveKey('pluginMethod')
        ->and($definition->beforeMethods['doSomething'])->toHaveKey('sortOrder')
        ->and($definition->beforeMethods['doSomething']['pluginMethod'])->toBe('doSomething')
        ->and($definition->beforeMethods['doSomething']['sortOrder'])->toBe(10);
});

it('extracts before methods with their sort orders', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->beforeMethods)
        ->toBeArray()
        ->toHaveKey('doSomething')
        ->and($definition->beforeMethods['doSomething']['sortOrder'])->toBe(10);
});

it('extracts target-method-keyed after methods from plugin class', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->afterMethods)
        ->toBeArray()
        ->toHaveKey('doSomethingAfter')
        ->and($definition->afterMethods['doSomethingAfter'])->toHaveKey('pluginMethod')
        ->and($definition->afterMethods['doSomethingAfter'])->toHaveKey('sortOrder')
        ->and($definition->afterMethods['doSomethingAfter']['pluginMethod'])->toBe('doSomethingAfter')
        ->and($definition->afterMethods['doSomethingAfter']['sortOrder'])->toBe(20);
});

it('extracts after methods with their sort orders', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(TargetServicePlugin::class);

    expect($definition->afterMethods)
        ->toBeArray()
        ->toHaveKey('doSomethingAfter')
        ->and($definition->afterMethods['doSomethingAfter']['sortOrder'])->toBe(20);
});

// Test fixture for class without Plugin attribute
class NotAPlugin
{
    public function doSomething(): void {}
}

it('throws PluginException when Plugin attribute missing target', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->parsePluginClass(NotAPlugin::class))
        ->toThrow(PluginException::class);
});

// Test fixture for class with Before/After attributes but no Plugin attribute
class InvalidPluginWithBeforeAfter
{
    /** @noinspection PhpUnused - Discovered via reflection */
    #[Before]
    public function beforeSomething(): void {}

    /** @noinspection PhpUnused - Discovered via reflection */
    #[After]
    public function afterSomething(): void {}
}

it('throws PluginException when before/after method on non-plugin class', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->validatePluginMethods(InvalidPluginWithBeforeAfter::class))
        ->toThrow(PluginException::class);
});

it('validates orphaned plugin methods still throws exception', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->validatePluginMethods(InvalidPluginWithBeforeAfter::class))
        ->toThrow(PluginException::class);
});

// Fixtures for new target-method-keyed structure tests

class SimpleTargetService
{
    public function save(): void {}
}

#[Plugin(target: SimpleTargetService::class)]
class SimpleTargetPlugin
{
    /** @noinspection PhpUnused */
    #[Before(sortOrder: 5)]
    public function save(): void {}

    /** @noinspection PhpUnused */
    #[After(sortOrder: 10)]
    public function save2(): void {}
}

it('resolves target method from plugin method name when no method param', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(SimpleTargetPlugin::class);

    expect($definition->beforeMethods)
        ->toHaveKey('save')
        ->and($definition->beforeMethods['save']['pluginMethod'])->toBe('save')
        ->and($definition->beforeMethods['save']['sortOrder'])->toBe(5);
});

class ExplicitMethodTargetService
{
    public function save(): void {}

    public function delete(): void {}
}

#[Plugin(target: ExplicitMethodTargetService::class)]
class ExplicitBeforePlugin
{
    /** @noinspection PhpUnused */
    #[Before(method: 'save', sortOrder: 3)]
    public function validateInput(): void {}
}

#[Plugin(target: ExplicitMethodTargetService::class)]
class ExplicitAfterPlugin
{
    /** @noinspection PhpUnused */
    #[After(method: 'delete', sortOrder: 7)]
    public function auditDelete(): void {}
}

it('resolves target method from Before attribute method param', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(ExplicitBeforePlugin::class);

    expect($definition->beforeMethods)
        ->toHaveKey('save')
        ->and($definition->beforeMethods['save']['pluginMethod'])->toBe('validateInput')
        ->and($definition->beforeMethods['save']['sortOrder'])->toBe(3);
});

it('resolves target method from After attribute method param', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(ExplicitAfterPlugin::class);

    expect($definition->afterMethods)
        ->toHaveKey('delete')
        ->and($definition->afterMethods['delete']['pluginMethod'])->toBe('auditDelete')
        ->and($definition->afterMethods['delete']['sortOrder'])->toBe(7);
});

class MappingTargetService
{
    public function process(): void {}
}

#[Plugin(target: MappingTargetService::class)]
class MappingPlugin
{
    /** @noinspection PhpUnused */
    #[Before(method: 'process', sortOrder: 1)]
    public function validateProcess(): void {}
}

it('builds PluginDefinition with correct target-to-plugin method mapping', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(MappingPlugin::class);

    expect($definition->pluginClass)->toBe(MappingPlugin::class)
        ->and($definition->targetClass)->toBe(MappingTargetService::class)
        ->and($definition->beforeMethods)->toHaveKey('process')
        ->and($definition->beforeMethods['process']['pluginMethod'])->toBe('validateProcess')
        ->and($definition->beforeMethods['process']['sortOrder'])->toBe(1);
});

class MixedTargetService
{
    public function run(): void {}

    public function stop(): void {}
}

#[Plugin(target: MixedTargetService::class)]
class MixedPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function run(): void {}

    /** @noinspection PhpUnused */
    #[After(method: 'stop', sortOrder: 15)]
    public function logStop(): void {}
}

it('handles mixed standard and explicit method params in same plugin', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(MixedPlugin::class);

    expect($definition->beforeMethods)
        ->toHaveKey('run')
        ->and($definition->beforeMethods['run']['pluginMethod'])->toBe('run')
        ->and($definition->beforeMethods['run']['sortOrder'])->toBe(0)
        ->and($definition->afterMethods)->toHaveKey('stop')
        ->and($definition->afterMethods['stop']['pluginMethod'])->toBe('logStop')
        ->and($definition->afterMethods['stop']['sortOrder'])->toBe(15);
});

// Fixtures for duplicate hook detection tests

class DuplicateBeforeTargetService
{
    public function save(): void {}
}

#[Plugin(target: DuplicateBeforeTargetService::class)]
class DuplicateBeforePlugin
{
    /** @noinspection PhpUnused */
    #[Before(method: 'save')]
    public function validateInput(): void {}

    /** @noinspection PhpUnused */
    #[Before(method: 'save')]
    public function sanitizeInput(): void {}
}

it('throws PluginException when two before methods in same class target the same method', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->parsePluginClass(DuplicateBeforePlugin::class))
        ->toThrow(PluginException::class);
});

class DuplicateAfterTargetService
{
    public function save(): void {}
}

#[Plugin(target: DuplicateAfterTargetService::class)]
class DuplicateAfterPlugin
{
    /** @noinspection PhpUnused */
    #[After(method: 'save')]
    public function logSave(): void {}

    /** @noinspection PhpUnused */
    #[After(method: 'save')]
    public function auditSave(): void {}
}

it('throws PluginException when two after methods in same class target the same method', function (): void {
    $discovery = new PluginDiscovery();

    expect(fn () => $discovery->parsePluginClass(DuplicateAfterPlugin::class))
        ->toThrow(PluginException::class);
});

class SameMethodBeforeAfterTargetService
{
    public function save(): void {}
}

#[Plugin(target: SameMethodBeforeAfterTargetService::class)]
class SameMethodBeforeAfterPlugin
{
    /** @noinspection PhpUnused */
    #[Before(method: 'save')]
    public function beforeSave(): void {}

    /** @noinspection PhpUnused */
    #[After(method: 'save')]
    public function afterSave(): void {}
}

it('allows before and after targeting the same method via method param in same class', function (): void {
    $discovery = new PluginDiscovery();
    $definition = $discovery->parsePluginClass(SameMethodBeforeAfterPlugin::class);

    expect($definition->beforeMethods)->toHaveKey('save')
        ->and($definition->afterMethods)->toHaveKey('save');
});

it('provides helpful error message with class and method names for intra-class conflict', function (): void {
    $discovery = new PluginDiscovery();

    try {
        $discovery->parsePluginClass(DuplicateBeforePlugin::class);
        expect(false)->toBeTrue('Expected PluginException to be thrown');
    } catch (PluginException $e) {
        expect($e->getMessage())->toContain('DuplicateBeforePlugin')
            ->and($e->getMessage())->toContain('before')
            ->and($e->getMessage())->toContain('save')
            ->and($e->getContext())->toContain('DuplicateBeforePlugin')
            ->and($e->getSuggestion())->not->toBeEmpty();
    }
});

class PDSubjectTarget
{
    public function save(
        string $sql = '',
    ): void {}
}

#[Plugin(target: PDSubjectTarget::class)]
class PDSubjectBeforePlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(
        #[Subject]
        PDSubjectTarget $subject,
        string $sql = '',
    ): void {}
}

it('records that a before method takes the subject when its first parameter is marked Subject', function (): void {
    $definition = (new PluginDiscovery())->parsePluginClass(PDSubjectBeforePlugin::class);

    expect($definition->beforeMethods['save'])->toBe([
        'pluginMethod' => 'save',
        'sortOrder' => 0,
        'subject' => true,
    ]);
});

#[Plugin(target: PDSubjectTarget::class)]
class PDPlainPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(
        string $sql = '',
    ): void {}

    /** @noinspection PhpUnused */
    #[After]
    public function load(): void {}
}

it('records that a method without a Subject parameter does not take the subject', function (): void {
    $definition = (new PluginDiscovery())->parsePluginClass(PDPlainPlugin::class);

    expect($definition->beforeMethods['save']['subject'])->toBeFalse()
        ->and($definition->afterMethods['load']['subject'])->toBeFalse();
});

#[Plugin(target: PDSubjectTarget::class)]
class PDSubjectSecondPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(
        string $sql,
        #[Subject]
        PDSubjectTarget $subject,
    ): void {}
}

it('throws when the Subject parameter is not the first parameter', function (): void {
    try {
        (new PluginDiscovery())->parsePluginClass(PDSubjectSecondPlugin::class);
        expect(false)->toBeTrue('Expected PluginException to be thrown');
    } catch (PluginException $e) {
        expect($e->getMessage())->toContain('PDSubjectSecondPlugin')
            ->toContain('save')
            ->toContain('$subject')
            ->toContain('position 1')
            ->and($e->getContext())->toContain('PDSubjectSecondPlugin')
            ->and($e->getSuggestion())->toContain('first parameter');
    }
});

#[Plugin(target: PDSubjectTarget::class)]
class PDSubjectVariadicPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(
        #[Subject]
        PDSubjectTarget ...$subject,
    ): void {}
}

#[Plugin(target: PDSubjectTarget::class)]
class PDSubjectByReferencePlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(
        #[Subject]
        PDSubjectTarget &$subject,
    ): void {}
}

it('throws when the Subject parameter is variadic or passed by reference', function (string $pluginClass): void {
    try {
        (new PluginDiscovery())->parsePluginClass($pluginClass);
        expect(false)->toBeTrue('Expected PluginException to be thrown');
    } catch (PluginException $e) {
        expect($e->getMessage())->toContain($pluginClass)
            ->toContain('save')
            ->toContain('$subject')
            ->toContain('position 0')
            ->and($e->getContext())->toContain($pluginClass)
            ->and($e->getSuggestion())->toContain('plain');
    }
})->with([
    'variadic' => PDSubjectVariadicPlugin::class,
    'by reference' => PDSubjectByReferencePlugin::class,
]);

#[Plugin(target: PDSubjectTarget::class)]
class PDSubjectNonHookPlugin
{
    /** @noinspection PhpUnused */
    public function helper(
        #[Subject]
        PDSubjectTarget $subject,
    ): void {}
}

it(
    'throws when a plugin class method marked with a Subject parameter has no Before or After attribute',
    function (): void {
        try {
            (new PluginDiscovery())->parsePluginClass(PDSubjectNonHookPlugin::class);
            expect(false)->toBeTrue('Expected PluginException to be thrown');
        } catch (PluginException $e) {
            expect($e->getMessage())->toContain('PDSubjectNonHookPlugin')
                ->toContain('helper')
                ->toContain('$subject')
                ->and($e->getContext())->toContain('PDSubjectNonHookPlugin')
                ->and($e->getSuggestion())->toContain('#[Before]');
        }
    },
);

#[Plugin(target: PDSubjectTarget::class)]
class PDSubjectAfterPlugin
{
    /** @noinspection PhpUnused */
    #[After(sortOrder: 5)]
    public function save(
        #[Subject]
        PDSubjectTarget $subject,
        mixed $result,
        string $sql = '',
    ): mixed {
        return $result;
    }
}

it('records that an after method takes the subject when its first parameter is marked Subject', function (): void {
    $definition = (new PluginDiscovery())->parsePluginClass(PDSubjectAfterPlugin::class);

    expect($definition->afterMethods['save'])->toBe([
        'pluginMethod' => 'save',
        'sortOrder' => 5,
        'subject' => true,
    ]);
});

interface PDTypeBaseInterface {}

interface PDTypeInterface extends PDTypeBaseInterface {}

interface PDTypeOtherInterface {}

class PDTypeParent {}

class PDTypeTarget extends PDTypeParent implements PDTypeInterface
{
    public function save(): void {}
}

class PDTypeSubtypeOfTarget extends PDTypeTarget {}

class PDTypeImplementation implements PDTypeInterface {}

class PDTypeUnrelated {}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeIntPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] int $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeStringPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] string $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeArrayPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] array $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeIterablePlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] iterable $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeCallablePlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] callable $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeBoolPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] bool $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeNullPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] null $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeSelfPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] self $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeUnrelatedPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeUnrelated $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeSubtypeOfClassTargetPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeSubtypeOfTarget $subject): void {}
}

#[Plugin(target: PDTypeInterface::class)]
class PDTypeSubtypeOfInterfaceTargetPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeImplementation $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeUnionOfRejectedPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] int|string|PDTypeUnrelated|null $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeIntersectionWithRejectedPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeInterface&PDTypeOtherInterface $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeUntypedPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeObjectPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] object $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeMixedPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] mixed $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeExactPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeTarget $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeNullableExactPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] ?PDTypeTarget $subject): void {}
}

it(
    'accepts a Subject parameter that is untyped or typed as object, mixed, the target or a nullable target',
    function (string $pluginClass): void {
        $definition = (new PluginDiscovery())->parsePluginClass($pluginClass);

        expect($definition->beforeMethods['save']['subject'])->toBeTrue();
    },
)->with([
    'untyped' => PDTypeUntypedPlugin::class,
    'object' => PDTypeObjectPlugin::class,
    'mixed' => PDTypeMixedPlugin::class,
    'the target' => PDTypeExactPlugin::class,
    'a nullable target' => PDTypeNullableExactPlugin::class,
]);

#[Plugin(target: PDTypeTarget::class)]
class PDTypeParentClassPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeParent $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeImplementedInterfacePlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeInterface $subject): void {}
}

#[Plugin(target: PDTypeInterface::class)]
class PDTypeExtendedInterfacePlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeBaseInterface $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeParentKeywordPlugin extends PDTypeParent
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] parent $subject): void {}
}

it(
    'accepts a Subject parameter typed as a parent class or an interface the target implements',
    function (string $pluginClass): void {
        $definition = (new PluginDiscovery())->parsePluginClass($pluginClass);

        expect($definition->beforeMethods['save']['subject'])->toBeTrue();
    },
)->with([
    'a parent class' => PDTypeParentClassPlugin::class,
    'an implemented interface' => PDTypeImplementedInterfacePlugin::class,
    'an interface the target interface extends' => PDTypeExtendedInterfacePlugin::class,
    'the parent keyword' => PDTypeParentKeywordPlugin::class,
]);

#[Plugin(target: PDTypeTarget::class)]
class PDTypeUnionPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] int|PDTypeUnrelated|PDTypeInterface $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeNullableUnionPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeUnrelated|PDTypeParent|null $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeIntersectionPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] PDTypeInterface&PDTypeBaseInterface $subject): void {}
}

#[Plugin(target: PDTypeTarget::class)]
class PDTypeDnfPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] (PDTypeInterface&PDTypeBaseInterface)|int $subject): void {}
}

it(
    'accepts a Subject parameter whose union or intersection type can hold the target',
    function (string $pluginClass): void {
        $definition = (new PluginDiscovery())->parsePluginClass($pluginClass);

        expect($definition->beforeMethods['save']['subject'])->toBeTrue();
    },
)->with([
    'a union with an acceptable member' => PDTypeUnionPlugin::class,
    'a nullable union' => PDTypeNullableUnionPlugin::class,
    'an intersection of acceptable members' => PDTypeIntersectionPlugin::class,
    'a DNF type' => PDTypeDnfPlugin::class,
]);

#[Plugin(target: 'PDTypeMissing\NoSuchTarget')]
class PDTypeMissingTargetPlugin
{
    /** @noinspection PhpUnused */
    #[Before]
    public function save(#[Subject] int $subject): void {}
}

it('skips the Subject type check when the plugin target does not exist', function (): void {
    $definition = (new PluginDiscovery())->parsePluginClass(PDTypeMissingTargetPlugin::class);

    expect($definition->beforeMethods['save']['subject'])->toBeTrue();
});

it(
    'throws when the Subject parameter is typed as a scalar, array, an unrelated class or a subtype of an interface target',
    function (string $pluginClass, string $declaredType, string $targetClass): void {
        try {
            (new PluginDiscovery())->parsePluginClass($pluginClass);
            expect(false)->toBeTrue('Expected PluginException to be thrown');
        } catch (PluginException $e) {
            expect($e->getMessage())->toContain($pluginClass)
                ->toContain('save')
                ->toContain('$subject')
                ->toContain($declaredType)
                ->toContain($targetClass)
                ->and($e->getContext())->toContain($pluginClass)
                ->and($e->getSuggestion())->toContain($targetClass);
        }
    },
)->with([
    'int' => [PDTypeIntPlugin::class, 'int', PDTypeTarget::class],
    'string' => [PDTypeStringPlugin::class, 'string', PDTypeTarget::class],
    'array' => [PDTypeArrayPlugin::class, 'array', PDTypeTarget::class],
    'iterable' => [PDTypeIterablePlugin::class, 'iterable', PDTypeTarget::class],
    'callable' => [PDTypeCallablePlugin::class, 'callable', PDTypeTarget::class],
    'bool' => [PDTypeBoolPlugin::class, 'bool', PDTypeTarget::class],
    'null' => [PDTypeNullPlugin::class, 'null', PDTypeTarget::class],
    'self' => [PDTypeSelfPlugin::class, PDTypeSelfPlugin::class, PDTypeTarget::class],
    'unrelated class' => [PDTypeUnrelatedPlugin::class, PDTypeUnrelated::class, PDTypeTarget::class],
    'subtype of a class target' => [
        PDTypeSubtypeOfClassTargetPlugin::class,
        PDTypeSubtypeOfTarget::class,
        PDTypeTarget::class,
    ],
    'subtype of an interface target' => [
        PDTypeSubtypeOfInterfaceTargetPlugin::class,
        PDTypeImplementation::class,
        PDTypeInterface::class,
    ],
    'union of rejected members' => [
        PDTypeUnionOfRejectedPlugin::class,
        PDTypeUnrelated::class . '|string|int|null',
        PDTypeTarget::class,
    ],
    'intersection with a rejected member' => [
        PDTypeIntersectionWithRejectedPlugin::class,
        PDTypeInterface::class . '&' . PDTypeOtherInterface::class,
        PDTypeTarget::class,
    ],
]);
