<?php

declare(strict_types=1);

namespace Marko\DevAi\Process;

class StdinPrompter implements ConfirmationPrompterInterface
{
    /**
     * @param resource $stream
     * @param resource $output
     */
    public function __construct(
        private $stream = STDIN,
        private readonly bool $noInteraction = false,
        private $output = STDOUT,
    ) {}

    public function isInteractive(): bool
    {
        return !$this->noInteraction && stream_isatty($this->stream);
    }

    public function confirm(
        string $question,
        bool $default,
    ): bool {
        $hint = $default ? '[Y/n]' : '[y/N]';
        fwrite($this->output, "$question $hint ");

        $line = fgets($this->stream);
        $answer = strtolower(trim($line !== false ? $line : ''));

        if ($answer === 'y' || $answer === 'yes') {
            return true;
        }

        if ($answer === 'n' || $answer === 'no') {
            return false;
        }

        return $default;
    }
}
