<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Core\Command\Output;
use Marko\Core\Path\ProjectPaths;

class ComposerPackageInstaller
{
    public function __construct(
        private readonly ProjectPaths $paths,
    ) {}

    /**
     * @param list<string> $packages
     * @return list<string>
     */
    public function missingPackages(array $packages): array
    {
        return array_values(array_filter(
            array_unique($packages),
            fn (string $package): bool => ! $this->isInstalled($package),
        ));
    }

    /**
     * @param list<string> $packages
     */
    public function ensureInstalled(
        array $packages,
        bool $dryRun,
        Output $output,
    ): int {
        $missing = $this->missingPackages($packages);

        if ($missing === []) {
            return 0;
        }

        if ($dryRun) {
            foreach ($missing as $package) {
                $output->writeLine(sprintf('Would install Composer package `%s`', $package));
            }

            return 0;
        }

        $output->writeLine(sprintf('Installing Composer packages: %s', implode(', ', $missing)));

        return $this->runCommand($this->composerRequireCommand($missing), $output);
    }

    protected function isInstalled(string $package): bool
    {
        [$vendor, $name] = explode('/', $package, 2);

        return is_dir($this->paths->vendor . '/' . $vendor . '/' . $name);
    }

    /**
     * @param list<string> $packages
     */
    protected function composerRequireCommand(array $packages): string
    {
        $parts = ['composer', 'require'];

        foreach ($packages as $package) {
            $parts[] = escapeshellarg($package);
        }

        return implode(' ', $parts);
    }

    protected function runCommand(
        string $command,
        Output $output,
    ): int {
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes, $this->paths->base);

        if (! is_resource($process)) {
            $output->writeLine('Failed to start Composer.');

            return 1;
        }

        foreach ([1, 2] as $index) {
            while (($line = fgets($pipes[$index])) !== false) {
                $output->write(rtrim($line, "\n") . "\n");
            }
            fclose($pipes[$index]);
        }

        return proc_close($process);
    }
}
