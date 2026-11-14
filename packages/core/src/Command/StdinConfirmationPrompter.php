<?php

declare(strict_types=1);

namespace Marko\Core\Command;

/**
 * Asks through the running command's Output and reads the answer from standard input.
 *
 * Input and Output are the running command's own (CommandRunner registers them in the
 * container), so --no-interaction and the output stream match what the command sees.
 * Piped input (`echo y | marko ...`) answers the question; isInteractive() still reports
 * false there, because no terminal is attached.
 */
readonly class StdinConfirmationPrompter implements ConfirmationPrompterInterface
{
    private const array YES = ['y', 'yes'];

    private const array NO = ['n', 'no'];

    /**
     * @param resource|null $stream Stream to read answers from; standard input when null
     */
    public function __construct(
        private Input $input,
        private Output $output,
        private mixed $stream = null,
    ) {}

    public function isInteractive(): bool
    {
        return $this->input->isInteractive()
            && stream_isatty($this->stream());
    }

    public function confirm(
        string $question,
        bool $default = false,
    ): bool {
        if (!$this->input->isInteractive()) {
            return $default;
        }

        $hint = $default ? '[Y/n]' : '[y/N]';
        $this->output->write("$question $hint ");

        $line = fgets($this->stream());
        $answer = strtolower(trim($line !== false ? $line : ''));

        if (in_array($answer, self::YES, true)) {
            return true;
        }

        if (in_array($answer, self::NO, true)) {
            return false;
        }

        return $default;
    }

    /**
     * @return resource
     */
    private function stream(): mixed
    {
        return $this->stream ?? STDIN;
    }
}
