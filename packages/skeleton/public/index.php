<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marko\Core\Application;

$app = Application::boot(dirname(__DIR__));
$app->handleRequest();
