<?php

declare(strict_types=1);

namespace Marko\DevAi\Installation;

use Marko\DevAi\Exceptions\DevAiInstallException;

interface IntelephenseEnsurerInterface
{
    /**
     * @throws DevAiInstallException
     */
    public function ensure(bool $skip = false): EnsureResult;
}
