<?php

declare(strict_types=1);

namespace Marko\AuthenticationPasskey\Ceremony;

/**
 * What a verified assertion yields: the authenticator's new signature counter.
 */
readonly class VerifiedAssertion
{
    /**
     * @param int $signCount The counter from the authenticator data (0 when it keeps none)
     */
    public function __construct(
        public int $signCount,
    ) {}
}
