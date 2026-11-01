<?php

declare(strict_types=1);

namespace Marko\Session;

use Marko\Core\Contracts\ResettableInterface;
use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\InvalidSessionIdException;
use Marko\Session\Exceptions\SessionException;
use Marko\Session\Exceptions\SessionNotStartedException;
use Marko\Session\Flash\FlashBag;
use Marko\Session\Flash\SessionFlashData;
use Override;

class Session implements SessionInterface, ResettableInterface
{
    public private(set) bool $started = false;

    private string $id = '';

    /**
     * Prepared by SessionMiddleware to start on first access.
     */
    private bool $armed = false;

    private ?FlashBag $flashBag = null;

    private bool $handlerRegistered = false;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * Snapshot of the data loaded by start(), compared by isModified().
     *
     * @var array<string, mixed>
     */
    private array $loadedData = [];

    private string $loadedId = '';

    public function __construct(
        private readonly SessionHandlerInterface $handler,
        private readonly SessionConfig $config,
    ) {}

    /**
     * @throws SessionException
     */
    public function start(): void
    {
        if ($this->started) {
            return;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            throw new SessionException(
                message: 'A session is already active',
                context: 'session_status() returned PHP_SESSION_ACTIVE',
                suggestion: 'Do not call session_start() manually when using the Session class',
            );
        }

        $this->configure();

        // Always set the internal session id explicitly, even when $this->id is
        // empty. PHP's session module keeps the last-used id in process memory
        // after session_write_close(); without this call a subsequent start()
        // in the same long-running process would silently resume the previous
        // request's session instead of generating a fresh one.
        session_id($this->id);

        if (!session_start()) {
            throw new SessionException(
                message: 'Failed to start session',
                context: 'session_start() returned false',
                suggestion: 'Check session configuration and handler setup',
            );
        }

        $this->id = session_id();
        $this->loadedData = $_SESSION ?? [];
        // Aging the flash data on load (not on access) lets next-request values
        // expire on the request after, whether or not anything read them. The
        // snapshot is taken before aging, so aged data counts as a modification
        // and is persisted.
        $this->data = SessionFlashData::age($this->loadedData);
        $this->loadedId = $this->id;
        $this->flashBag = new FlashBag($this->data);
        $this->started = true;
        $this->armed = false;
    }

    public function arm(): void
    {
        $this->armed = true;
    }

    public function isAvailable(): bool
    {
        return $this->started || $this->armed;
    }

    public function isModified(): bool
    {
        return $this->started
            && ($this->data !== $this->loadedData || $this->id !== $this->loadedId);
    }

    /**
     * @throws SessionNotStartedException|SessionException
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $this->ensureStarted('get');

        return $this->data[$key] ?? $default;
    }

    /**
     * @throws SessionNotStartedException|SessionException
     */
    public function set(
        string $key,
        mixed $value,
    ): void {
        $this->ensureStarted('set');

        $this->data[$key] = $value;
    }

    /**
     * @throws SessionNotStartedException|SessionException
     */
    public function has(
        string $key,
    ): bool {
        $this->ensureStarted('has');

        return array_key_exists($key, $this->data);
    }

    /**
     * @throws SessionNotStartedException|SessionException
     */
    public function remove(
        string $key,
    ): void {
        $this->ensureStarted('remove');

        unset($this->data[$key]);
    }

    /**
     * @throws SessionNotStartedException|SessionException
     */
    public function clear(): void
    {
        $this->ensureStarted('clear');

        $this->data = [];
        $this->flashBag = new FlashBag($this->data);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws SessionNotStartedException|SessionException
     */
    public function all(): array
    {
        $this->ensureStarted('all');

        return $this->data;
    }

    /**
     * @throws SessionNotStartedException|SessionException
     */
    public function regenerate(
        bool $deleteOldSession = true,
    ): void {
        $this->ensureStarted('regenerate');

        if (!session_regenerate_id($deleteOldSession)) {
            throw new SessionException(
                message: 'Failed to regenerate session ID',
                context: 'session_regenerate_id() returned false',
                suggestion: 'Check session configuration and permissions',
            );
        }

        $this->id = session_id();
    }

    /**
     * @throws SessionNotStartedException|SessionException
     */
    public function destroy(): void
    {
        if (!$this->started && $this->armed) {
            // Nothing was started, so there is nothing stored to destroy.
            $this->armed = false;

            return;
        }

        $this->ensureStarted('destroy');

        $this->data = [];
        $this->flashBag = null;

        session_destroy();

        $this->started = false;
        $this->armed = false;
        $this->id = '';
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @throws InvalidSessionIdException|SessionException
     */
    public function setId(
        string $id,
    ): void {
        if ($this->started) {
            throw new SessionException(
                message: 'Cannot set session ID after session has started',
                context: 'Session is already started',
                suggestion: 'Call setId() before calling start()',
            );
        }

        if (!$this->validateId($id)) {
            throw InvalidSessionIdException::forId($id);
        }

        $this->id = $id;
    }

    /**
     * @throws SessionNotStartedException|SessionException
     */
    public function flash(): FlashBag
    {
        $this->ensureStarted('flash');

        return $this->flashBag;
    }

    public function save(): void
    {
        $this->armed = false;

        if (!$this->started) {
            return;
        }

        $_SESSION = $this->data;
        session_write_close();
        $this->started = false;
    }

    public function discard(): void
    {
        $this->armed = false;

        if (!$this->started) {
            return;
        }

        session_abort();
        $this->started = false;
    }

    #[Override]
    public function reset(): void
    {
        $this->armed = false;
        $this->id = '';
        $this->data = [];
        $this->loadedData = [];
        $this->loadedId = '';
        $this->flashBag = null;
    }

    private function configure(): void
    {
        // Note: session.save_handler is automatically set to 'user' by session_set_save_handler()
        ini_set('session.gc_maxlifetime', (string) ($this->config->lifetime() * 60));
        ini_set('session.gc_probability', (string) $this->config->gcProbability());
        ini_set('session.gc_divisor', (string) $this->config->gcDivisor());
        // Strict mode makes PHP ask the handler's validateId() before resuming
        // an inbound id, so an unknown or expired id gets a fresh one instead
        // of being adopted. Lazy writes make PHP call updateTimestamp()
        // rather than write() when the data is unchanged.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.lazy_write', '1');
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '1');

        session_name($this->config->cookieName());

        // session_set_save_handler()'s $register_shutdown argument registers a
        // register_shutdown_function() callback as a side effect. Registering
        // it more than once per process would accumulate an unbounded number
        // of shutdown callbacks in a long-running worker, so this must happen
        // at most once, not on every start().
        if (!$this->handlerRegistered) {
            session_set_save_handler($this->handler, true);
            $this->handlerRegistered = true;
        }
    }

    /**
     * Start an armed session on first access. A session nobody armed fails
     * loudly: only SessionMiddleware prepares a session, so access outside it
     * never starts one behind the caller's back.
     *
     * @throws SessionNotStartedException|SessionException
     */
    private function ensureStarted(
        string $operation,
    ): void {
        if ($this->started) {
            return;
        }

        if (!$this->armed) {
            throw SessionNotStartedException::forOperation($operation);
        }

        $this->start();
    }

    private function validateId(
        string $id,
    ): bool {
        return preg_match('/^[a-zA-Z0-9-]{32,128}$/', $id) === 1;
    }
}
