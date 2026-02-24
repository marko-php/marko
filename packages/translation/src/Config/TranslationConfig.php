<?php

declare(strict_types=1);

namespace Marko\Translation\Config;

use Marko\Config\ConfigRepositoryInterface;

readonly class TranslationConfig
{
    public string $locale;

    public string $fallbackLocale;

    public function __construct(
        ConfigRepositoryInterface $config,
    ) {
        $this->locale = $config->getString('translation.locale');
        $this->fallbackLocale = $config->getString('translation.fallback_locale');
    }
}
