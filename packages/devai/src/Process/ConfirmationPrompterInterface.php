<?php

declare(strict_types=1);

namespace Marko\DevAi\Process;

interface ConfirmationPrompterInterface
{
    public function isInteractive(): bool;

    public function confirm(string $question, bool $default): bool;
}
