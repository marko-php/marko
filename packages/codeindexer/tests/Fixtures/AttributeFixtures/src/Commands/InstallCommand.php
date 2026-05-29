<?php

declare(strict_types=1);

namespace Fixture\AttributeFixtures\Commands;

use Marko\Core\Attributes\Command;

#[Command(name: 'fixture:install', description: 'Install fixtures')]
class InstallCommand
{
    public function handle(): void {}
}
