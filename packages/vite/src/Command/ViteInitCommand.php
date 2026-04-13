<?php

declare(strict_types=1);

namespace Marko\Vite\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Exceptions\BindingException;
use Marko\Vite\ComposerPackageInstaller;
use Marko\Vite\Contracts\VitePublisherInterface;
use Marko\Vite\PackageJsonUpdater;
use Marko\Vite\ViteInitPrompter;
use Marko\Vite\ViteInitSelections;
use Marko\Vite\ValueObjects\FilePublishResult;
use Marko\Vite\ValueObjects\PackageJsonUpdateResult;
use Marko\Vite\ValueObjects\ViteConfig;
use Symfony\Component\Console\Output\StreamOutput;
use function Termwind\render;
use function Termwind\renderUsing;

#[Command(name: 'vite:init', description: 'Ensure the minimum package.json setup required for Vite')]
readonly class ViteInitCommand implements CommandInterface
{
    public function __construct(
        private PackageJsonUpdater $packageJson,
        private VitePublisherInterface $publisher,
        private ContainerInterface $container,
        private ViteInitPrompter $prompter,
        private ComposerPackageInstaller $packageInstaller,
        private ViteConfig $viteConfig,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int
    {
        $force = $input->hasOption('force') || $input->hasOption('f');
        $dryRun = $input->hasOption('dry-run');
        $skipInstall = $input->hasOption('skip-install');
        $selections = $this->prompter->resolve($input, $output);

        $this->renderIntro($selections, $dryRun, $output);

        if ($selections->requiresAddonScaffolding()) {
            $status = $this->runAddonScaffolding($selections, $force, $dryRun, $skipInstall, $output);
            $this->renderCompletion($status, $output);

            return $status;
        }

        $result = $this->packageJson->update(
            fields: [
                'private' => true,
                'type' => 'module',
            ],
            scripts: [
                'dev' => $this->viteDevScript(),
                'build' => $this->viteBuildScript(),
            ],
            devDependencies: [
                'vite' => 'latest',
            ],
            force: $force,
            dryRun: $dryRun,
        );

        $this->report($result, $dryRun, $output);
        $configResult = $this->publisher->publishConfig(
            force: $force,
            dryRun: $dryRun,
        );
        $entrypointResult = $this->publisher->publishJsEntrypoint(
            force: $force,
            dryRun: $dryRun,
        );

        $this->reportConfig($configResult, $output);
        $this->reportConfig($entrypointResult, $output);
        $status = $this->hasFailedPublish($configResult, $entrypointResult) ? 1 : 0;
        $this->renderCompletion($status, $output);

        return $status;
    }

    private function report(
        PackageJsonUpdateResult $result,
        bool $dryRun,
        Output $output,
    ): void
    {
        if ($result->createdFile) {
            $this->writeStatusLine(
                $output,
                $dryRun ? 'plan' : 'ok',
                $dryRun ? 'Would create package.json' : 'Created package.json'
            );
        }

        foreach ($result->added as $change) {
            $this->writeStatusLine($output, 'ok', sprintf('Added %s', $change));
        }

        foreach ($result->updated as $change) {
            $this->writeStatusLine($output, 'ok', sprintf('Updated %s', $change));
        }

        foreach ($result->alreadyPresent as $change) {
            $this->writeStatusLine($output, 'info', sprintf('%s already present', ucfirst($change)));
        }

        foreach ($result->skipped as $change) {
            $this->writeStatusLine($output, 'warn', sprintf('Skipped %s', $change));
        }
    }

    private function reportConfig(
        FilePublishResult $result,
        Output $output,
    ): void
    {
        match ($result->status) {
            'created' => $this->writeStatusLine($output, 'ok', sprintf('Published %s', $result->path)),
            'replaced' => $this->writeStatusLine($output, 'ok', sprintf('Replaced %s', $result->path)),
            'would_create' => $this->writeStatusLine($output, 'plan', sprintf('Would publish %s', $result->path)),
            'would_replace' => $this->writeStatusLine($output, 'plan', sprintf('Would replace %s', $result->path)),
            'already_present' => $this->writeStatusLine($output, 'info', sprintf('%s already present', $result->path)),
            'skipped' => $this->writeStatusLine(
                $output,
                'warn',
                sprintf('Skipped %s because it already exists', $result->path)
            ),
            'failed' => $this->writeStatusLine(
                $output,
                'error',
                $result->message ?? sprintf('Failed to publish %s', $result->path)
            ),
            default => null,
        };
    }

    private function runAddonScaffolding(
        ViteInitSelections $selections,
        bool $force,
        bool $dryRun,
        bool $skipInstall,
        Output $output,
    ): int {
        $missingPackages = $this->packageInstaller->missingPackages($selections->composerPackages());

        if ($dryRun && $missingPackages !== []) {
            foreach ($missingPackages as $package) {
                $this->writeStatusLine($output, 'plan', sprintf('Would install Composer package `%s`', $package));
            }

            if ($selections->inertiaPreset !== null) {
                $this->writeStatusLine($output, 'plan', sprintf(
                    'Would continue `marko vite:init --inertia=%s%s%s --dry-run` after installing `%s`',
                    $selections->inertiaPreset,
                    $selections->tailwind ? ' --tailwind' : '',
                    $force ? ' --force' : '',
                    $this->inertiaPackage($selections->inertiaPreset),
                ));
            }

            if ($selections->tailwind && $selections->inertiaPreset === null) {
                $this->writeStatusLine(
                    $output,
                    'plan',
                    'Would continue `marko vite:init --tailwind' . ($force ? ' --force' : '') . ' --dry-run` after installing `marko/tailwindcss`'
                );
            }

            return 0;
        }

        if ($missingPackages !== [] && ! $skipInstall) {
            $installStatus = $this->packageInstaller->ensureInstalled($selections->composerPackages(), false, $output);

            if ($installStatus !== 0) {
                return 1;
            }

            return $this->rerunViteInit($selections, $force, $output);
        }

        $status = 0;

        if ($selections->inertiaPreset !== null) {
            $status = $this->applyInertiaPreset($selections->inertiaPreset, $force, $dryRun, $output);
        }

        if ($status !== 0) {
            return $status;
        }

        if ($selections->tailwind) {
            $status = $this->applyTailwindPreset($force, $dryRun, $output);
        }

        return $status;
    }

    private function rerunViteInit(
        ViteInitSelections $selections,
        bool $force,
        Output $output,
    ): int {
        $binary = $this->findMarkoBinary();

        if ($binary === null) {
            $this->writeStatusLine(
                $output,
                'error',
                'Could not find `vendor/bin/marko` to continue scaffolding after Composer install.'
            );

            return 1;
        }

        $command = [
            escapeshellarg(PHP_BINARY),
            escapeshellarg($binary),
            'vite:init',
            '--no-interaction',
            '--skip-install',
        ];

        if ($selections->inertiaPreset !== null) {
            $command[] = '--inertia=' . escapeshellarg($selections->inertiaPreset);
        }

        if ($selections->tailwind) {
            $command[] = '--tailwind';
        }

        if ($force) {
            $command[] = '--force';
        }

        $this->writeStatusLine($output, 'info', 'Continuing scaffold with the refreshed autoloader...');

        return $this->rerunAfterInstall(implode(' ', $command), $output);
    }

    private function inertiaPackage(string $preset): string
    {
        return match ($preset) {
            'vue' => 'marko/inertia-vue',
            'react' => 'marko/inertia-react',
            'svelte' => 'marko/inertia-svelte',
        };
    }

    private function applyTailwindPreset(
        bool $force,
        bool $dryRun,
        Output $output,
    ): int {
        $updaterClass = 'Marko\\TailwindCss\\TailwindViteConfigUpdater';
        $publisherInterface = 'Marko\\TailwindCss\\Contracts\\TailwindPublisherInterface';

        try {
            $updater = $this->container->get($updaterClass);
            $publisher = $this->container->get($publisherInterface);
        } catch (BindingException) {
            $this->writeStatusLine(
                $output,
                'error',
                'Skipped Tailwind preset because marko/tailwindcss is not installed'
            );

            return 1;
        } catch (\Throwable $exception) {
            $this->writeStatusLine($output, 'error', sprintf(
                'Tailwind preset failed during service resolution: %s',
                $exception->getMessage(),
            ));

            return 1;
        }

        try {
            $result = $this->packageJson->update(
                fields: [
                    'private' => true,
                    'type' => 'module',
                ],
                scripts: [
                    'dev' => $this->viteDevScript(),
                    'build' => $this->viteBuildScript(),
                ],
                devDependencies: [
                    'vite' => 'latest',
                    'tailwindcss' => 'latest',
                    '@tailwindcss/vite' => 'latest',
                ],
                force: $force,
                dryRun: $dryRun,
            );

            $configResult = $updater->ensureTailwindConfig($force, $dryRun);
            $cssResult = $publisher->publishCssEntrypoint($force, $dryRun);
        } catch (\Throwable $exception) {
            $this->writeStatusLine($output, 'error', sprintf(
                'Tailwind preset failed: %s',
                $exception->getMessage(),
            ));

            return 1;
        }

        $this->report($result, $dryRun, $output);
        $this->reportPresetConfig($configResult, 'Tailwind-aware Vite config', $output);
        $this->reportPresetConfig($cssResult, 'Tailwind CSS entrypoint', $output);

        return $this->hasFailedPublish($configResult, $cssResult) ? 1 : 0;
    }

    private function applyInertiaPreset(
        string $preset,
        bool $force,
        bool $dryRun,
        Output $output,
    ): int {
        $normalizedPreset = strtolower(trim($preset));

        $presets = [
            'vue' => [
                'package' => 'marko/inertia-vue',
                'devDependencies' => [
                    'vite' => 'latest',
                    'vue' => 'latest',
                    '@vitejs/plugin-vue' => 'latest',
                    '@inertiajs/vue3' => 'latest',
                ],
                'updaterClass' => 'Marko\\Inertia\\Vue\\InertiaVueViteConfigUpdater',
                'updaterMethod' => 'ensureVueConfig',
                'publisherInterface' => 'Marko\\Inertia\\Vue\\Contracts\\InertiaVuePublisherInterface',
                'label' => 'Vue-aware Vite config',
                'entrypointLabel' => 'Inertia Vue entrypoint',
            ],
            'react' => [
                'package' => 'marko/inertia-react',
                'devDependencies' => [
                    'vite' => 'latest',
                    'react' => 'latest',
                    'react-dom' => 'latest',
                    '@vitejs/plugin-react' => 'latest',
                    '@inertiajs/react' => 'latest',
                ],
                'updaterClass' => 'Marko\\Inertia\\React\\InertiaReactViteConfigUpdater',
                'updaterMethod' => 'ensureReactConfig',
                'publisherInterface' => 'Marko\\Inertia\\React\\Contracts\\InertiaReactPublisherInterface',
                'label' => 'React-aware Vite config',
                'entrypointLabel' => 'Inertia React entrypoint',
            ],
            'svelte' => [
                'package' => 'marko/inertia-svelte',
                'devDependencies' => [
                    'vite' => 'latest',
                    'svelte' => 'latest',
                    '@sveltejs/vite-plugin-svelte' => 'latest',
                    '@inertiajs/svelte' => 'latest',
                ],
                'updaterClass' => 'Marko\\Inertia\\Svelte\\InertiaSvelteViteConfigUpdater',
                'updaterMethod' => 'ensureSvelteConfig',
                'publisherInterface' => 'Marko\\Inertia\\Svelte\\Contracts\\InertiaSveltePublisherInterface',
                'label' => 'Svelte-aware Vite config',
                'entrypointLabel' => 'Inertia Svelte entrypoint',
            ],
        ];

        if (! isset($presets[$normalizedPreset])) {
            $this->writeStatusLine(
                $output,
                'error',
                sprintf('Unknown Inertia preset `%s`; expected vue, react, or svelte', $preset)
            );

            return 1;
        }

        $definition = $presets[$normalizedPreset];

        try {
            $updater = $this->container->get($definition['updaterClass']);
            $publisher = $this->container->get($definition['publisherInterface']);
        } catch (BindingException) {
            $this->writeStatusLine($output,
                'error',
                sprintf(
                    'Skipped Inertia preset `%s` because %s is not installed',
                    $normalizedPreset,
                    $definition['package'],
                )
            );

            return 1;
        } catch (\Throwable $exception) {
            $this->writeStatusLine($output,
                'error',
                sprintf(
                    'Inertia preset `%s` failed during service resolution: %s',
                    $normalizedPreset,
                    $exception->getMessage(),
                )
            );

            return 1;
        }

        try {
            $result = $this->packageJson->update(
                fields: [
                    'private' => true,
                    'type' => 'module',
                ],
                scripts: [
                    'dev' => $this->viteDevScript(),
                    'build' => $this->viteBuildScript(),
                ],
                devDependencies: $definition['devDependencies'],
                force: $force,
                dryRun: $dryRun,
            );

            $configResult = $updater->{$definition['updaterMethod']}($force, $dryRun);
            $entrypointResult = $publisher->publishJsEntrypoint($force, $dryRun);
        } catch (\Throwable $exception) {
            $this->writeStatusLine($output,
                'error',
                sprintf(
                    'Inertia preset `%s` failed: %s',
                    $normalizedPreset,
                    $exception->getMessage(),
                )
            );

            return 1;
        }

        $this->report($result, $dryRun, $output);
        $this->reportPresetConfig($configResult, $definition['label'], $output);
        $this->reportPresetConfig($entrypointResult, $definition['entrypointLabel'], $output);

        return $this->hasFailedPublish($configResult, $entrypointResult) ? 1 : 0;
    }

    private function viteDevScript(): string
    {
        return sprintf('vite --config ./%s', ltrim($this->viteConfig->rootViteConfigPath, './'));
    }

    private function viteBuildScript(): string
    {
        return sprintf('vite build --config ./%s', ltrim($this->viteConfig->rootViteConfigPath, './'));
    }

    private function reportPresetConfig(
        FilePublishResult $result,
        string $label,
        Output $output,
    ): void {
        match ($result->status) {
            'created' => $this->writeStatusLine($output, 'ok', sprintf('Published %s (%s)', $result->path, $label)),
            'replaced' => $this->writeStatusLine($output, 'ok', sprintf('Updated %s (%s)', $result->path, $label)),
            'would_create' => $this->writeStatusLine(
                $output,
                'plan',
                sprintf('Would publish %s (%s)', $result->path, $label)
            ),
            'would_replace' => $this->writeStatusLine(
                $output,
                'plan',
                sprintf('Would update %s (%s)', $result->path, $label)
            ),
            'already_present' => $this->writeStatusLine(
                $output,
                'info',
                sprintf('%s already includes %s', $result->path, $label)
            ),
            'skipped' => $this->writeStatusLine($output,
                'warn',
                sprintf(
                    'Skipped %s because it already exists with custom contents; use --force to replace it',
                    $result->path,
                )
            ),
            'failed' => $this->writeStatusLine(
                $output,
                'error',
                $result->message ?? sprintf('Failed to publish %s (%s)', $result->path, $label)
            ),
            default => null,
        };
    }

    private function hasFailedPublish(FilePublishResult ...$results): bool
    {
        foreach ($results as $result) {
            if ($result->status === 'failed') {
                return true;
            }
        }

        return false;
    }

    private function renderIntro(
        ViteInitSelections $selections,
        bool $dryRun,
        Output $output,
    ): void {
        if (! $this->supportsStyling()) {
            return;
        }

        $preset = $selections->inertiaPreset !== null ? 'Inertia ' . ucfirst($selections->inertiaPreset) : 'Vite';
        $tailwind = $selections->tailwind ? ' + Tailwind' : '';
        $mode = $dryRun ? 'Dry run' : 'Scaffold';

        $this->writeStyledBlock(
            $output,
            sprintf(
                '<div class="mb-1"><span class="font-bold text-cyan">Marko Frontend Scaffold</span></div><div><span class="text-gray">%s</span> <span class="text-green">%s%s</span></div>',
                htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($preset, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($tailwind, ENT_QUOTES, 'UTF-8'),
            )
        );
    }

    private function renderCompletion(
        int $status,
        Output $output,
    ): void {
        if (! $this->supportsStyling()) {
            return;
        }

        $this->writeStyledBlock($output,
            $status === 0
                ? '<div class="mt-1"><span class="font-bold text-green">Frontend scaffold complete.</span></div>'
                : '<div class="mt-1"><span class="font-bold text-red">Frontend scaffold stopped with an error.</span></div>'
        );
    }

    private function writeStatusLine(
        Output $output,
        string $kind,
        string $message,
    ): void {
        if (! $this->supportsStyling()) {
            $output->writeLine($message);

            return;
        }

        $badge = match ($kind) {
            'ok' => '<span class="px-1 mr-1 bg-green text-black">OK</span>',
            'info' => '<span class="px-1 mr-1 bg-blue text-black">INFO</span>',
            'warn' => '<span class="px-1 mr-1 bg-yellow text-black">SKIP</span>',
            'error' => '<span class="px-1 mr-1 bg-red text-white">ERR</span>',
            'plan' => '<span class="px-1 mr-1 bg-cyan text-black">PLAN</span>',
            default => '<span class="px-1 mr-1 bg-gray text-black">...</span>',
        };

        $this->writeStyledBlock($output,
            sprintf(
                '<div>%s<span>%s</span></div>',
                $badge,
                htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
            )
        );
    }

    private function writeStyledBlock(
        Output $output,
        string $html,
    ): void {
        if (! $this->supportsStyling()) {
            return;
        }

        renderUsing(new StreamOutput(STDOUT));
        render($html);
    }

    private function supportsStyling(): bool
    {
        if (! function_exists('Termwind\\render') || ! defined('STDOUT')) {
            return false;
        }

        if (function_exists('stream_isatty')) {
            return @stream_isatty(STDOUT);
        }

        if (function_exists('posix_isatty')) {
            return @posix_isatty(STDOUT);
        }

        return false;
    }

    private function findMarkoBinary(): ?string
    {
        $base = getcwd() ?: '.';
        $candidates = [
            $base . '/vendor/bin/marko',
            $base . '/packages/cli/bin/marko',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    protected function rerunAfterInstall(
        string $command,
        Output $output,
    ): int
    {
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            $output->writeLine(sprintf('Failed to start `%s`.', $command));

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
