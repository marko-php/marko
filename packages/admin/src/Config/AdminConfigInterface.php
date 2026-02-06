<?php

declare(strict_types=1);

namespace Marko\Admin\Config;

interface AdminConfigInterface
{
    public function getRoutePrefix(): string;

    public function getName(): string;
}
