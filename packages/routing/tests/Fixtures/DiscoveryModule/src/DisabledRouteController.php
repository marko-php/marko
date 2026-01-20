<?php

declare(strict_types=1);

namespace Test\DiscoveryModule;

use Marko\Routing\Attributes\DisableRoute;
use Marko\Routing\Attributes\Get;

class DisabledRouteController
{
    #[Get('/enabled')]
    public function enabled(): void {}

    #[Get('/disabled')]
    #[DisableRoute]
    public function disabled(): void {}
}
