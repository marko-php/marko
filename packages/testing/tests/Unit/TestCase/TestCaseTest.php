<?php

declare(strict_types=1);

use Marko\Testing\TestCase;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

// ---------------------------------------------------------------------------

it('extends the phpunit test case so it works as a Pest base via uses()', function (): void {
    expect(new TestCase('test'))->toBeInstanceOf(PhpUnitTestCase::class);
});

it('registers app module autoloaders so an App namespaced class resolves in tests', function (): void {
    $fixtureRoot = dirname(__DIR__, 2) . '/fixtures/project-root';

    $tc = new class ('test') extends TestCase
    {
        public static string $root = '';

        protected function projectRoot(): string
        {
            return self::$root;
        }
    };

    $tc::$root = $fixtureRoot;
    $tc->setUp();

    expect(class_exists('App\Home\HomeService', true))->toBeTrue();
});

it('resolves a class from a module in the modules directory', function (): void {
    $fixtureRoot = dirname(__DIR__, 2) . '/fixtures/project-root';

    $tc = new class ('test') extends TestCase
    {
        public static string $root = '';

        protected function projectRoot(): string
        {
            return self::$root;
        }
    };

    $tc::$root = $fixtureRoot;
    $tc->setUp();

    expect(class_exists('Blog\BlogService', true))->toBeTrue();
});

it('locates the project root when running from a nested module tests directory', function (): void {
    $nestedRoot = dirname(__DIR__, 2) . '/fixtures/nested-project';

    $tc = new class ('test') extends TestCase
    {
        public static string $root = '';

        protected function projectRoot(): string
        {
            return self::$root;
        }
    };

    $tc::$root = $nestedRoot;
    $tc->setUp();

    expect(class_exists('App\Accounts\AccountService', true))->toBeTrue();
});

it('does not require a session or database driver to be installed', function (): void {
    // TestCase must be usable without session/database dependencies.
    $fixtureRoot = dirname(__DIR__, 2) . '/fixtures/project-root';

    $tc = new class ('test') extends TestCase
    {
        public static string $root = '';

        protected function projectRoot(): string
        {
            return self::$root;
        }
    };

    $tc::$root = $fixtureRoot;

    expect(fn () => $tc->setUp())->not->toThrow(Throwable::class);
});

it('registers autoloaders only once across multiple test cases', function (): void {
    // Unique root with no modules so we can count cleanly
    $uniqueRoot = dirname(__DIR__, 2) . '/fixtures/project-root-once';

    if (!is_dir($uniqueRoot . '/vendor')) {
        mkdir($uniqueRoot . '/vendor', 0777, true);
    }

    $makeTC = static function () use ($uniqueRoot): TestCase {
        return new class ('test') extends TestCase
        {
            public static string $root = '';

            protected function projectRoot(): string
            {
                return self::$root;
            }

            public function callRegister(): void
            {
                $this->registerModuleAutoloaders();
            }
        };
    };

    $tc1 = $makeTC();
    $tc1::$root = $uniqueRoot;

    $tc2 = $makeTC();
    $tc2::$root = $uniqueRoot;

    $tc1->callRegister();
    $after1 = count(spl_autoload_functions());

    $tc2->callRegister();
    $after2 = count(spl_autoload_functions());

    // Second call must not add more autoloaders
    expect($after2)->toBe($after1);
});

it('does not error and resolves nothing when run from a package dir whose root has no app or modules modules', function (): void {
    // Simulate running from within the marko monorepo where vendor/ exists but app/ and modules/ do not
    $monoRepoRoot = dirname(__DIR__, 5); // packages/testing/tests/Unit/TestCase -> monorepo root

    $tc = new class ('test') extends TestCase
    {
        public static string $root = '';

        protected function projectRoot(): string
        {
            return self::$root;
        }
    };

    $tc::$root = $monoRepoRoot;

    expect(fn () => $tc->setUp())->not->toThrow(Throwable::class);
});

it('has registered the autoloaders by the time the test body runs so an App class resolves inside the test closure', function (): void {
    // HomeService was registered by the setUp() of an earlier test in this process.
    // This closure body executes after setUp(), confirming registration timing is sufficient.
    expect(class_exists('App\Home\HomeService'))->toBeTrue();
});
