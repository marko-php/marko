<?php

declare(strict_types=1);

namespace Marko\Session;

/**
 * Spies on registrations made by {@see Session::configure()} so tests can
 * prove session_set_save_handler() — which registers a session_write_close()
 * shutdown function as a side effect — is called at most once per process,
 * rather than accumulating a new shutdown callback per request in a
 * long-running worker.
 *
 * PHP resolves the unqualified call in Session::configure() against this
 * namespace before falling back to the global function. It always forwards to
 * the real built-in, so behaviour is unchanged for every other test.
 *
 * This lives in an autoload-dev `files` entry rather than inside the test that
 * reads the counter, and that placement is load-bearing: PHP caches the
 * resolved target on the call site's opline the first time it executes. If any
 * earlier test in the same process reached Session::configure() before this
 * shim was defined, the opline would be permanently bound to the global
 * function and the spy would silently never fire — producing a count of 0 and
 * a test that fails only depending on how paratest happened to distribute
 * files across worker processes.
 *
 * @noinspection PhpUnused
 */
function session_set_save_handler(mixed ...$arguments): bool
{
    $GLOBALS['sessionSetSaveHandlerCallCount'] = ($GLOBALS['sessionSetSaveHandlerCallCount'] ?? 0) + 1;

    return \session_set_save_handler(...$arguments);
}
