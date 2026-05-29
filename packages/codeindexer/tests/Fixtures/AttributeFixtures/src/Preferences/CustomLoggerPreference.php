<?php

declare(strict_types=1);

namespace Fixture\AttributeFixtures\Preferences;

use Marko\Core\Attributes\Preference;

#[Preference(replaces: 'Fixture\AttributeFixtures\Contracts\LoggerInterface')]
class CustomLoggerPreference
{
    public function log(string $message): void {}
}
