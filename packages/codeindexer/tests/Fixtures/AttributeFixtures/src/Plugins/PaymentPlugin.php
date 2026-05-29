<?php

declare(strict_types=1);

namespace Fixture\AttributeFixtures\Plugins;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;

#[Plugin(target: 'Fixture\AttributeFixtures\Services\PaymentService')]
class PaymentPlugin
{
    #[Before(sortOrder: 10, method: 'process')]
    public function beforeProcess(): void {}

    #[After(sortOrder: 20, method: 'process')]
    public function afterProcess(): void {}
}
