<?php

declare(strict_types=1);

namespace Fixture\AttributeFixtures\Observers;

use Marko\Core\Attributes\Observer;

#[Observer(event: 'Fixture\AttributeFixtures\Events\UserCreated', priority: 10)]
class UserCreatedObserver
{
    public function handle(): void {}
}
