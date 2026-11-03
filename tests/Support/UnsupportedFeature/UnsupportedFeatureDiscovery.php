<?php

declare(strict_types=1);

namespace Marko\Tests\Support\UnsupportedFeature;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use UnexpectedValueException;

/**
 * Discovers every PHP source file under `packages/*\/src`.
 *
 * Deliberately not restricted to `Exceptions/` directories: `packages/mail/src/Exception/`
 * is singular and `PluginArgumentCountException` sits in `Plugin/`. The detector decides
 * which declared classes matter.
 */
readonly class UnsupportedFeatureDiscovery
{
    /**
     * @return list<string>
     *
     * @throws UnexpectedValueException
     */
    public function discover(string $packagesRoot): array
    {
        $files = [];

        $entries = scandir($packagesRoot);

        if ($entries === false) {
            throw new UnexpectedValueException("Cannot list $packagesRoot, so no package sources can be checked");
        }

        foreach ($entries as $entry) {
            $srcDirectory = $packagesRoot . '/' . $entry . '/src';

            if ($entry === '.' || $entry === '..' || !is_dir($srcDirectory)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($srcDirectory, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($iterator as $fileInfo) {
                /** @var SplFileInfo $fileInfo */
                if ($fileInfo->isFile() && $fileInfo->getExtension() === 'php') {
                    $files[] = $fileInfo->getPathname();
                }
            }
        }

        sort($files);

        return $files;
    }
}
