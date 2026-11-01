<?php

declare(strict_types=1);

namespace Marko\Forms;

use Marko\Session\Contracts\FlashDataInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Validation\Validation\DataPath;
use Marko\Validation\Validation\ValidationErrors;

/**
 * Reads the form state FlashedFormStateWriter flashed on the previous request.
 *
 * When no session was started on this request there is no previous request to
 * read from (a cookieless visit, a CLI render, a queued mail), so the state is
 * empty. That also keeps every template render from starting a session just to
 * look for errors.
 */
readonly class SessionFlashedFormState implements FlashedFormStateInterface
{
    public function __construct(
        private FlashDataInterface $flashData,
        private SessionInterface $session,
    ) {}

    public function errors(): ValidationErrors
    {
        return new ValidationErrors($this->read(FlashedFormStateWriter::ERRORS_KEY));
    }

    public function hasErrors(): bool
    {
        return $this->errors()->isNotEmpty();
    }

    public function old(
        string $key,
        mixed $default = null,
    ): mixed {
        $input = $this->read(FlashedFormStateWriter::OLD_INPUT_KEY);

        return DataPath::has($input, $key) ? DataPath::get($input, $key) : $default;
    }

    /**
     * @return array<mixed>
     */
    private function read(
        string $key,
    ): array {
        if (!$this->session->started) {
            return [];
        }

        $value = $this->flashData->get($key);

        return is_array($value) ? $value : [];
    }
}
