<?php

declare(strict_types=1);

/**
 * Marko Framework Demo Application
 *
 * This demo exercises all core features:
 * - Module discovery and loading
 * - DI container with autowiring
 * - Interface bindings
 * - Preferences (class replacement)
 * - Plugins (before/after method interception)
 * - Events and observers
 */

// Autoload
require_once __DIR__ . '/../vendor/autoload.php';

use Demo\Custom\Observers\GreetingLogger;
use Demo\Greeter\Contracts\GreeterInterface;
use Demo\Greeter\DefaultGreeter;
use Demo\Greeter\Events\GreetingCreated;
use Marko\Core\Application;
use Marko\Core\Plugin\PluginInterceptor;

echo "=== Marko Framework Demo ===\n\n";

// Bootstrap the application
$basePath = dirname(__DIR__);
$app = new Application(
    vendorPath: $basePath . '/vendor',
    modulesPath: $basePath . '/modules',
    appPath: $basePath . '/app',
);
$app->boot();

echo "1. Module Loading\n";
echo "-----------------\n";
$modules = $app->getModules();
echo 'Loaded ' . count($modules) . " modules:\n";
foreach ($modules as $module) {
    echo "  - {$module->name} ({$module->version}) from {$module->source}\n";
}
echo "\n";

echo "2. DI Container & Bindings\n";
echo "--------------------------\n";
$container = $app->getContainer();
$greeter = $container->get(GreeterInterface::class);
echo 'Resolved GreeterInterface to: ' . $greeter::class . "\n";
echo "\n";

echo "3. Preferences (Class Replacement)\n";
echo "----------------------------------\n";
$defaultGreeter = $container->get(DefaultGreeter::class);
echo 'Requested DefaultGreeter, got: ' . $defaultGreeter::class . "\n";
echo 'Greeting: ' . $defaultGreeter->greet('World') . "\n";
echo "\n";

echo "4. Plugins (Method Interception)\n";
echo "--------------------------------\n";
// Create interceptor and proxy for DefaultGreeter
$interceptor = new PluginInterceptor($container, $app->getPluginRegistry());
$proxiedGreeter = $interceptor->createProxy(DefaultGreeter::class, $defaultGreeter);
$pluginResult = $proxiedGreeter->greet('Framework User');
echo "With plugin: $pluginResult\n";
echo "\n";

echo "5. Events & Observers\n";
echo "---------------------\n";
$dispatcher = $app->getEventDispatcher();
$event = new GreetingCreated(
    greeting: 'Hello, Event System!',
    name: 'Event Tester',
);
$dispatcher->dispatch($event);
echo "Dispatched GreetingCreated event\n";
echo "Observer logs:\n";
foreach (GreetingLogger::$logs as $log) {
    echo "  - $log\n";
}
echo "\n";

echo "=== Demo Complete ===\n";
echo "All Marko core features are working!\n";
