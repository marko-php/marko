<?php

declare(strict_types=1);

use Marko\Core\Discovery\ClassFileParser;

it('extracts class name from file with namespace', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

class UserService
{
    public function getUser(): void {}
}
PHP;
    file_put_contents($tempDir . '/UserService.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/UserService.php');

    expect($className)->toBe('App\\Services\\UserService');

    unlink($tempDir . '/UserService.php');
    rmdir($tempDir);
});

it('extracts class name from file without namespace', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

class GlobalClass
{
    public function doSomething(): void {}
}
PHP;
    file_put_contents($tempDir . '/GlobalClass.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/GlobalClass.php');

    expect($className)->toBe('GlobalClass');

    unlink($tempDir . '/GlobalClass.php');
    rmdir($tempDir);
});

it('returns null for file without class', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

function helper(): void {}
PHP;
    file_put_contents($tempDir . '/helpers.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/helpers.php');

    expect($className)->toBeNull();

    unlink($tempDir . '/helpers.php');
    rmdir($tempDir);
});

it('returns null for non-existent file', function (): void {
    $parser = new ClassFileParser();
    $className = $parser->extractClassName('/nonexistent/file.php');

    expect($className)->toBeNull();
});

it('finds php files recursively', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir . '/subdir/nested', 0755, true);

    file_put_contents($tempDir . '/Root.php', '<?php class Root {}');
    file_put_contents($tempDir . '/subdir/Sub.php', '<?php class Sub {}');
    file_put_contents($tempDir . '/subdir/nested/Deep.php', '<?php class Deep {}');
    file_put_contents($tempDir . '/readme.txt', 'Not a PHP file');

    $parser = new ClassFileParser();
    $files = iterator_to_array($parser->findPhpFiles($tempDir));

    expect($files)->toHaveCount(3);

    $filenames = array_map(fn ($f) => $f->getFilename(), $files);
    expect($filenames)->toContain('Root.php')
        ->toContain('Sub.php')
        ->toContain('Deep.php')
        ->not->toContain('readme.txt');

    // Cleanup
    unlink($tempDir . '/Root.php');
    unlink($tempDir . '/subdir/Sub.php');
    unlink($tempDir . '/subdir/nested/Deep.php');
    unlink($tempDir . '/readme.txt');
    rmdir($tempDir . '/subdir/nested');
    rmdir($tempDir . '/subdir');
    rmdir($tempDir);
});

it('returns empty iterator for non-existent directory', function (): void {
    $parser = new ClassFileParser();
    $files = iterator_to_array($parser->findPhpFiles('/nonexistent/directory'));

    expect($files)->toBeEmpty();
});

it('extracts the class name when a docblock above the class contains the word "class"', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Services;

/**
 * This docblock mentions the word class many times.
 * A class should be used for grouping. See class examples.
 */
class RealClass
{
    public function doSomething(): void {}
}
PHP;
    file_put_contents($tempDir . '/RealClass.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/RealClass.php');

    expect($className)->toBe('App\\Services\\RealClass');

    unlink($tempDir . '/RealClass.php');
    rmdir($tempDir);
});

it('extracts the class name when the file body contains the word "class" inside a string literal', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Http;

class Controller
{
    public function getDescription(): string
    {
        return 'This class is a controller class for handling requests';
    }
}
PHP;
    file_put_contents($tempDir . '/Controller.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/Controller.php');

    expect($className)->toBe('App\\Http\\Controller');

    unlink($tempDir . '/Controller.php');
    rmdir($tempDir);
});

it('returns the fully qualified name combining namespace and class', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace Vendor\Module\Domain;

class Entity
{
}
PHP;
    file_put_contents($tempDir . '/Entity.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/Entity.php');

    expect($className)->toBe('Vendor\\Module\\Domain\\Entity');

    unlink($tempDir . '/Entity.php');
    rmdir($tempDir);
});

it('extracts the name of a final class declaration', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Security;

final class FirewallRule
{
}
PHP;
    file_put_contents($tempDir . '/FirewallRule.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/FirewallRule.php');

    expect($className)->toBe('App\\Security\\FirewallRule');

    unlink($tempDir . '/FirewallRule.php');
    rmdir($tempDir);
});

it('extracts the name of an abstract class declaration', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Base;

abstract class AbstractHandler
{
}
PHP;
    file_put_contents($tempDir . '/AbstractHandler.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/AbstractHandler.php');

    expect($className)->toBe('App\\Base\\AbstractHandler');

    unlink($tempDir . '/AbstractHandler.php');
    rmdir($tempDir);
});

it('extracts the name of a readonly class declaration', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Dto;

readonly class UserDto
{
    public function __construct(
        public string $name,
    ) {}
}
PHP;
    file_put_contents($tempDir . '/UserDto.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/UserDto.php');

    expect($className)->toBe('App\\Dto\\UserDto');

    unlink($tempDir . '/UserDto.php');
    rmdir($tempDir);
});

it('extracts the name of an interface, a trait, and an enum declaration', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $interfaceCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Contracts;

interface HandlerInterface
{
}
PHP;
    $traitCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Concerns;

trait HasTimestamps
{
}
PHP;
    $enumCode = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Enums;

enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
PHP;
    file_put_contents($tempDir . '/HandlerInterface.php', $interfaceCode);
    file_put_contents($tempDir . '/HasTimestamps.php', $traitCode);
    file_put_contents($tempDir . '/Status.php', $enumCode);

    $parser = new ClassFileParser();

    expect($parser->extractClassName($tempDir . '/HandlerInterface.php'))->toBe('App\\Contracts\\HandlerInterface')
        ->and($parser->extractClassName($tempDir . '/HasTimestamps.php'))->toBe('App\\Concerns\\HasTimestamps')
        ->and($parser->extractClassName($tempDir . '/Status.php'))->toBe('App\\Enums\\Status');

    unlink($tempDir . '/HandlerInterface.php');
    unlink($tempDir . '/HasTimestamps.php');
    unlink($tempDir . '/Status.php');
    rmdir($tempDir);
});

it('returns null for a file that declares no class, interface, trait, or enum', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace App\Helpers;

function formatDate(string $date): string
{
    return date('Y-m-d', strtotime($date));
}

const VERSION = '1.0.0';
PHP;
    file_put_contents($tempDir . '/helpers.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/helpers.php');

    expect($className)->toBeNull();

    unlink($tempDir . '/helpers.php');
    rmdir($tempDir);
});

it(
    'ignores ::class constant references and new class anonymous-class expressions when no real top-level type is declared',
    function (): void {
        $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
        mkdir($tempDir, 0755, true);

        $code = <<<'PHP'
<?php

    declare(strict_types=1);

    namespace App\Factories;

    use App\Contracts\HandlerInterface;

    function makeHandler(): HandlerInterface
{
    $type = HandlerInterface::class;

        return new class () implements HandlerInterface {};
}
PHP;
        file_put_contents($tempDir . '/factories.php', $code);

        $parser = new ClassFileParser();
        $className = $parser->extractClassName($tempDir . '/factories.php');

        expect($className)->toBeNull();

        unlink($tempDir . '/factories.php');
        rmdir($tempDir);
    },
);

it('handles deeply nested namespaces', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . bin2hex(random_bytes(8));
    mkdir($tempDir, 0755, true);

    $code = <<<'PHP'
<?php

declare(strict_types=1);

namespace Vendor\Package\Domain\Entity\User;

class UserEntity
{
}
PHP;
    file_put_contents($tempDir . '/UserEntity.php', $code);

    $parser = new ClassFileParser();
    $className = $parser->extractClassName($tempDir . '/UserEntity.php');

    expect($className)->toBe('Vendor\\Package\\Domain\\Entity\\User\\UserEntity');

    unlink($tempDir . '/UserEntity.php');
    rmdir($tempDir);
});
