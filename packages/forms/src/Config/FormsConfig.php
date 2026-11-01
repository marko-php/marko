<?php

declare(strict_types=1);

namespace Marko\Forms\Config;

use Marko\Config\ConfigRepositoryInterface;
use Marko\Forms\Exceptions\FormsException;

readonly class FormsConfig
{
    public function __construct(
        private ConfigRepositoryInterface $config,
    ) {}

    /**
     * Dot paths of the input fields that are never flashed.
     *
     * @return list<string>
     *
     * @throws FormsException When an entry is not a non-empty string
     */
    public function neverFlash(): array
    {
        $fields = [];

        foreach ($this->config->getArray('forms.never_flash') as $field) {
            if (!is_string($field) || trim($field) === '') {
                throw FormsException::invalidNeverFlashField($field);
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * The path to redirect to when the request has no same-origin Referer.
     *
     * @throws FormsException When the value is not a local path
     */
    public function fallbackRedirect(): string
    {
        $path = $this->config->getString('forms.fallback_redirect');

        // '//host' and '/\host' are protocol-relative URLs to another site in browsers
        if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\')) {
            throw FormsException::invalidFallbackRedirect($path);
        }

        return $path;
    }
}
