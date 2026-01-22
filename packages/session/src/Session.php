<?php

declare(strict_types=1);

namespace Marko\Session;

use Marko\Session\Config\SessionConfig;
use Marko\Session\Contracts\SessionHandlerInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Exceptions\InvalidSessionIdException;
use Marko\Session\Exceptions\SessionException;
use Marko\Session\Exceptions\SessionNotStartedException;
use Marko\Session\Flash\FlashBag;

class Session implements SessionInterface
{
    private bool $started = false;

    private string $id = '';

    private ?FlashBag $flashBag = null;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    public function __construct(
        private readonly SessionHandlerInterface $handler,
        private readonly SessionConfig $config,
    ) {}

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

        if ($this->id !== '') {
            session_id($this->id);
        }

        if (!session_start()) {
            throw new SessionException(
                message: 'Failed to start session',
                context: 'session_start() returned false',
                suggestion: 'Check session configuration and handler setup',
            );
        }

        $this->id = session_id();
        $this->data = $_SESSION ?? [];
        $this->flashBag = new FlashBag($this->data);
        $this->started = true;
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $this->ensureStarted('get');

        return $this->data[$key] ?? $default;
    }

    public function set(
        string $key,
        mixed $value,
    ): void {
        $this->ensureStarted('set');

        $this->data[$key] = $value;
    }

    public function has(
        string $key,
    ): bool {
        $this->ensureStarted('has');

        return array_key_exists($key, $this->data);
    }

    public function remove(
        string $key,
    ): void {
        $this->ensureStarted('remove');

        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->ensureStarted('clear');

        $this->data = [];
        $this->flashBag = new FlashBag($this->data);
    }

    public function all(): array
    {
        $this->ensureStarted('all');

        return $this->data;
    }

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

    public function destroy(): void
    {
        $this->ensureStarted('destroy');

        $this->data = [];
        $this->flashBag = null;

        session_destroy();

        $this->started = false;
        $this->id = '';

        // Clear session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly'],
            );
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

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

    public function flash(): FlashBag
    {
        $this->ensureStarted('flash');

        return $this->flashBag;
    }

    public function save(): void
    {
        if (!$this->started) {
            return;
        }

        $_SESSION = $this->data;
        session_write_close();
    }

    private function configure(): void
    {
        ini_set('session.save_handler', 'user');
        ini_set('session.gc_maxlifetime', (string) ($this->config->lifetime() * 60));
        ini_set('session.gc_probability', (string) $this->config->gcProbability());
        ini_set('session.gc_divisor', (string) $this->config->gcDivisor());
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');

        session_name($this->config->cookieName());

        session_set_cookie_params([
            'lifetime' => $this->config->expireOnClose() ? 0 : $this->config->lifetime() * 60,
            'path' => $this->config->cookiePath(),
            'domain' => $this->config->cookieDomain() ?? '',
            'secure' => $this->config->cookieSecure(),
            'httponly' => $this->config->cookieHttpOnly(),
            'samesite' => ucfirst($this->config->cookieSameSite()),
        ]);

        session_set_save_handler($this->handler, true);
    }

    private function ensureStarted(
        string $operation,
    ): void {
        if (!$this->started) {
            throw SessionNotStartedException::forOperation($operation);
        }
    }

    private function validateId(
        string $id,
    ): bool {
        return preg_match('/^[a-zA-Z0-9-]{32,128}$/', $id) === 1;
    }
}
