<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Command\ViewClearCommand;
use Marko\View\ViewConfig;

/**
 * @return array{0: int, 1: string}
 */
function runViewClear(
    string $projectRoot,
    string $cacheDirectory,
): array {
    $command = new ViewClearCommand(
        new ViewConfig(new FakeConfigRepository(['view.cache_directory' => $cacheDirectory])),
        new CacheDirectoryGuard(new ProjectPaths($projectRoot)),
    );
    $stream = fopen('php://memory', 'w+');
    $exitCode = $command->execute(new Input(['marko', 'view:clear']), new Output($stream));
    rewind($stream);

    return [$exitCode, stream_get_contents($stream)];
}

describe('ViewClearCommand', function (): void {
    it('is registered as a destructive view:clear command', function (): void {
        $attribute = new ReflectionClass(ViewClearCommand::class)->getAttributes(Command::class)[0]->newInstance();

        expect($attribute->name)->toBe('view:clear')
            ->and($attribute->destructive)->toBeTrue();
    });

    it('removes every compiled template and keeps the cache directory', function (): void {
        $root = sys_get_temp_dir() . '/marko-view-clear-' . bin2hex(random_bytes(8));
        mkdir($root . '/storage/views/ab', 0700, true);
        file_put_contents($root . '/storage/views/main.latte--1a2b.php', '<?php');
        file_put_contents($root . '/storage/views/main.latte--1a2b.php.lock', '');
        file_put_contents($root . '/storage/views/ab/cd1234.php', '<?php');

        [$exitCode, $output] = runViewClear($root, 'storage/views');

        expect($exitCode)->toBe(0)
            ->and(is_dir($root . '/storage/views'))->toBeTrue()
            ->and(scandir($root . '/storage/views'))->toBe(['.', '..'])
            ->and($output)->toContain('Removed 3 compiled template files from ' . $root . '/storage/views');

        rmdir($root . '/storage/views');
        rmdir($root . '/storage');
        rmdir($root);
    });

    it('succeeds on an empty cache directory', function (): void {
        $root = sys_get_temp_dir() . '/marko-view-clear-' . bin2hex(random_bytes(8));
        mkdir($root, 0700, true);

        [$exitCode, $output] = runViewClear($root, 'storage/views');

        expect($exitCode)->toBe(0)
            ->and($output)->toContain('Removed 0 compiled template files');

        rmdir($root . '/storage/views');
        rmdir($root . '/storage');
        rmdir($root);
    });
});
