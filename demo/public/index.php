<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Marko\Routing\Http\Request;

$app = (require __DIR__ . '/../vendor/marko/core/bootstrap.php')(
    vendorPath: __DIR__ . '/../vendor',
    modulesPath: __DIR__ . '/../modules',
    appPath: __DIR__ . '/../app',
);

// Create Request from globals
$request = Request::fromGlobals();

// Route request through Router
$router = $app->getRouter();
$response = $router->handle($request);

// Send Response to client
$response->send();
