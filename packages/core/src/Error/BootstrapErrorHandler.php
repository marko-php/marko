<?php

declare(strict_types=1);

namespace Marko\Core\Error;

use Closure;
use Marko\Core\Environment\AppEnvironment;
use Throwable;

/**
 * A minimal, safe exception handler for the boot phase.
 *
 * Application::initialize() registers it before anything else runs (.env
 * loading, discovery, module.php requires, boot callbacks), so an exception
 * thrown before an errors module has registered its own handler never reaches
 * PHP's native "Uncaught ... Stack trace" output.
 *
 * Outside development (an unset environment counts as production) it answers
 * with a generic 500 and no details, turns display_errors off, and writes the
 * full exception to error_log(). In development it shows the exception's
 * class and message, escaped.
 *
 * The environment is resolved when an exception is handled, so a value set by
 * the .env file is honoured once it has been loaded.
 *
 * An errors module's handler replaces it: the module calls unregister() before
 * registering its own handler, so the bootstrap handler is not left underneath.
 */
class BootstrapErrorHandler
{
    public const string GENERIC_MESSAGE = 'Server Error';

    public const int EXIT_STATUS = 255;

    private ?Closure $handler = null;

    private string|false|null $originalDisplayErrors = null;

    public function __construct(
        private readonly AppEnvironment $appEnvironment = new AppEnvironment(),
    ) {}

    /**
     * Install the handler and turn display_errors off outside development.
     */
    public function register(): void
    {
        if ($this->handler !== null) {
            return;
        }

        // A handled uncaught exception would otherwise end the process with status 0;
        // keep PHP's failure status so CLI callers and deploy scripts see the boot failed.
        $this->handler = function (Throwable $throwable): void {
            $this->handle($throwable);

            exit(self::EXIT_STATUS);
        };
        set_exception_handler($this->handler);
        $this->applyDisplayErrors();
    }

    /**
     * Remove the handler when it is still the active exception handler.
     *
     * A handler installed on top of it afterwards is left untouched, so this
     * never removes another component's handler.
     */
    public function unregister(): void
    {
        if (!$this->isActive()) {
            return;
        }

        restore_exception_handler();
        $this->handler = null;
    }

    /**
     * Whether this handler is the currently active exception handler.
     */
    public function isActive(): bool
    {
        if ($this->handler === null) {
            return false;
        }

        // PHP has no getter for the active handler: push a placeholder to read
        // the current one back, then pop the placeholder.
        $current = set_exception_handler(null);
        restore_exception_handler();

        return $current === $this->handler;
    }

    /**
     * Re-apply the display_errors setting for the current environment.
     *
     * Called again after .env has been loaded, since the environment may only
     * be known from that file.
     */
    public function applyDisplayErrors(): void
    {
        if (!$this->appEnvironment->isDevelopment()) {
            $this->originalDisplayErrors ??= ini_get('display_errors');
            ini_set('display_errors', '0');

            return;
        }

        if (is_string($this->originalDisplayErrors)) {
            ini_set('display_errors', $this->originalDisplayErrors);
            $this->originalDisplayErrors = null;
        }
    }

    public function handle(
        Throwable $throwable,
    ): void {
        error_log('Uncaught exception during application boot: ' . $throwable);

        $development = $this->appEnvironment->isDevelopment();
        $json = $this->acceptsJson();

        if (
            PHP_SAPI !== 'cli'
            && !headers_sent()
        ) {
            http_response_code(500);
            header('Content-Type: ' . ($json ? 'application/json' : 'text/plain; charset=UTF-8'));
        }

        echo $this->render($throwable, $development, $json);
    }

    private function render(
        Throwable $throwable,
        bool $development,
        bool $json,
    ): string {
        if (!$development) {
            return $json
                ? (string) json_encode(['message' => self::GENERIC_MESSAGE])
                : self::GENERIC_MESSAGE . "\n";
        }

        $detail = $throwable::class . ': ' . $throwable->getMessage();

        if ($json) {
            return (string) json_encode(
                ['message' => self::GENERIC_MESSAGE, 'exception' => $detail],
                JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
            );
        }

        return self::GENERIC_MESSAGE . ': ' . htmlspecialchars($detail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . "\n";
    }

    private function acceptsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';

        if (!is_string($accept)) {
            return false;
        }

        return str_contains($accept, 'application/json')
            || str_contains($accept, '+json');
    }
}
