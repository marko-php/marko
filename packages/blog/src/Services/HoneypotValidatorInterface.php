<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

/**
 * Interface for honeypot-based spam detection.
 *
 * Honeypot fields are hidden from humans but filled by bots,
 * allowing detection of automated form submissions without CAPTCHAs.
 */
interface HoneypotValidatorInterface
{
    /**
     * Get the current honeypot field name.
     */
    public function getFieldName(): string;

    /**
     * Validate the honeypot field value.
     *
     * Returns true if the submission is valid (honeypot is empty),
     * false if it appears to be spam (honeypot has a value).
     */
    public function validate(
        string $honeypotValue,
    ): bool;

    /**
     * Render the honeypot field HTML for embedding in forms.
     */
    public function renderField(): string;
}
