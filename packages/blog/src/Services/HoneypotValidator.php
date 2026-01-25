<?php

declare(strict_types=1);

namespace Marko\Blog\Services;

/**
 * Default honeypot validator for spam prevention.
 *
 * Field names rotate daily based on a secret and date combination
 * to make it harder for bots to learn and target specific field names.
 */
class HoneypotValidator implements HoneypotValidatorInterface
{
    private const string DEFAULT_SECRET = 'marko-honeypot';
    private const string FIELD_PREFIX = 'hp_';

    public function __construct(
        private string $secret = self::DEFAULT_SECRET,
    ) {}

    public function getFieldName(): string
    {
        // Generate a rotating field name based on date and secret
        // Changes daily to prevent bots from caching field names
        $dateKey = date('Y-m-d');
        $hash = substr(hash('sha256', $this->secret . $dateKey), 0, 8);

        return self::FIELD_PREFIX . $hash;
    }

    public function validate(
        string $honeypotValue,
    ): bool {
        return $honeypotValue === '';
    }

    public function renderField(): string
    {
        $fieldName = $this->getFieldName();
        $style = 'position:absolute;left:-9999px;';

        return '<div style="' . $style . '">'
            . '<input type="text" name="' . $fieldName . '" value="" autocomplete="off" tabindex="-1" />'
            . '</div>';
    }
}
