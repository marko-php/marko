<?php

declare(strict_types=1);

namespace Marko\View\Command;

use FilesystemIterator;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Support\ErrorCapture;
use Marko\View\CacheDirectoryGuard;
use Marko\View\Exceptions\InsecureCacheDirectoryException;
use Marko\View\Exceptions\ViewException;
use Marko\View\ViewConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Removes every compiled template from the configured view cache directory.
 *
 * Every view driver compiles into view.cache_directory, so clearing it is the
 * same for every engine. The directory itself is kept.
 *
 * @noinspection PhpUnused
 */
#[Command(name: 'view:clear', description: 'Remove every compiled template from the view cache', destructive: true)]
readonly class ViewClearCommand implements CommandInterface
{
    public function __construct(
        private ViewConfig $viewConfig,
        private CacheDirectoryGuard $cacheDirectoryGuard,
    ) {}

    /**
     * @throws InsecureCacheDirectoryException|ViewException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $directory = $this->cacheDirectoryGuard->prepare($this->viewConfig->cacheDirectory());
        $removed = 0;

        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        /** @var SplFileInfo $entry */
        foreach ($entries as $entry) {
            $path = $entry->getPathname();

            if ($entry->isDir() && !$entry->isLink()) {
                $this->remove($path, fn (): bool => rmdir($path));

                continue;
            }

            $this->remove($path, fn (): bool => unlink($path));
            $removed++;
        }

        $output->writeLine("Removed $removed compiled template files from $directory.");

        return 0;
    }

    /**
     * @param callable(): bool $operation
     * @throws ViewException
     */
    private function remove(
        string $path,
        callable $operation,
    ): void {
        if (!ErrorCapture::run($reason, $operation)) {
            throw new ViewException(
                message: "Could not remove '$path' from the view cache.",
                context: $reason ?? '',
                suggestion: 'Check that the user running this command owns the view cache directory and everything in it.',
            );
        }
    }
}
