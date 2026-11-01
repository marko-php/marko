<?php

declare(strict_types=1);

namespace Marko\Testing\Http;

use PHPUnit\Framework\Assert;

/**
 * Counts a passed TestResponse or TestClient assertion with PHPUnit when it is
 * loaded, so a test whose only checks are those assertions is not reported as
 * risky ("did not perform any assertions"). Without PHPUnit this is a no-op.
 */
class AssertionCounter
{
    public static function passed(): void
    {
        if (class_exists(Assert::class)) {
            Assert::assertTrue(true);
        }
    }
}
