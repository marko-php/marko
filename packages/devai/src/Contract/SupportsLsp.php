<?php

declare(strict_types=1);

namespace Marko\DevAi\Contract;

use Marko\DevAi\ValueObject\LspRegistration;

interface SupportsLsp
{
    public function registerLspServer(LspRegistration $registration, string $projectRoot): void;
}
