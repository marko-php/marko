<?php

declare(strict_types=1);

// The real marko/errors-simple wiring: its boot callback registers a global error
// handler and exception handler, its shutdown callback removes them.
//
// errors-simple/ -> marko/ -> vendor/ -> errors-app/ -> fixtures/ -> tests/ -> testing/ -> packages/
return require dirname(__DIR__, 7) . '/errors-simple/module.php';
