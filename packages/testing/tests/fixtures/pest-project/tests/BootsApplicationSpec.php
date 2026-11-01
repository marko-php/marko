<?php

declare(strict_types=1);

use Marko\Testing\Http\TestClient;

// A plain it() test: no TestCase, no hooks in Pest.php. The errors-app fixture's boot pushes
// the real marko/errors-simple error and exception handlers; marko/testing must remove them
// after the test, or PHPUnit reports it as risky (and failOnRisky fails the run).
it('boots an application without leaving its error handlers installed', function (): void {
    $client = TestClient::boot(dirname(__DIR__, 2) . '/errors-app');

    expect($client->application()->modules)->not->toBeEmpty();
});
