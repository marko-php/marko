<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Symfony\Component\Console\Output\StreamOutput;
use function Termwind\render;
use function Termwind\renderUsing;

class ViteInitPrompter
{
    /** @var resource */
    private mixed $inputStream;

    public function __construct(
        mixed $inputStream = null,
    ) {
        $this->inputStream = $inputStream ?? STDIN;
    }

    public function resolve(
        Input $input,
        Output $output,
    ): ViteInitSelections {
        $flagInertia = $input->getOption('inertia');
        $flagTailwind = $input->hasOption('tailwind');
        $normalizedFlagInertia = $this->normalizePreset($flagInertia);

        if ($flagInertia !== null && $normalizedFlagInertia === null && ! $this->isExplicitNonePreset($flagInertia)) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown Inertia preset `%s`; expected `vue`, `react`, `svelte`, or `none`.',
                trim($flagInertia),
            ));
        }

        if ($flagInertia !== null || $flagTailwind || $input->hasOption(
            'no-interaction'
        ) || ! $this->isInteractive()) {
            return new ViteInitSelections($normalizedFlagInertia, $flagTailwind);
        }

        $this->writeTermwind(
            $output,
            '<div class="mb-1"><span class="font-bold text-cyan">Frontend scaffold options</span></div>',
            'Frontend scaffold options:',
        );
        $inertiaPreset = $this->askInertiaPreset($output);
        $tailwind = $this->askTailwind($output);

        return new ViteInitSelections($inertiaPreset, $tailwind);
    }

    protected function isInteractive(): bool
    {
        if (defined('STDIN') && function_exists('stream_isatty')) {
            return @stream_isatty(STDIN);
        }

        if (defined('STDIN') && function_exists('posix_isatty')) {
            return @posix_isatty(STDIN);
        }

        return false;
    }

    protected function askInertiaPreset(Output $output): ?string
    {
        while (true) {
            $output->write('Use Inertia? [none/vue/react/svelte] ');
            $answer = $this->readLine();

            if ($answer === null || $answer === '' || $answer === 'none' || $answer === 'n') {
                return null;
            }

            $normalized = $this->normalizePreset($answer);

            if ($normalized !== null) {
                return $normalized;
            }

            $output->writeLine('Please enter `none`, `vue`, `react`, or `svelte`.');
        }
    }

    protected function askTailwind(Output $output): bool
    {
        while (true) {
            $output->write('Add Tailwind CSS? [y/N] ');
            $answer = $this->readLine();

            if ($answer === null || $answer === '' || $answer === 'n' || $answer === 'no') {
                return false;
            }

            if ($answer === 'y' || $answer === 'yes') {
                return true;
            }

            $output->writeLine('Please enter `y` or `n`.');
        }
    }

    protected function readLine(): ?string
    {
        $line = fgets($this->inputStream);

        if ($line === false) {
            return null;
        }

        return strtolower(trim($line));
    }

    protected function normalizePreset(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return match ($normalized) {
            '', 'none' => null,
            'vue', 'react', 'svelte' => $normalized,
            default => null,
        };
    }

    private function isExplicitNonePreset(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['', 'none'], true);
    }

    private function writeTermwind(
        Output $output,
        string $html,
        string $fallback,
    ): void {
        if (! $this->isInteractive() || ! function_exists('Termwind\\parse')) {
            $output->writeLine($fallback);

            return;
        }

        renderUsing(new StreamOutput(STDOUT));
        render($html);
    }
}
