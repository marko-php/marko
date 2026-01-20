<?php

declare(strict_types=1);

use Marko\Core\Discovery\ClassFileParser;

it('extracts class name from file with namespace', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
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
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
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
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
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
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
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

it('handles deeply nested namespaces', function (): void {
    $tempDir = sys_get_temp_dir() . '/marko_test_' . uniqid();
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
