<?php

declare(strict_types=1);

namespace Marko\Forms;

use Marko\Forms\Config\FormsConfig;
use Marko\Forms\Exceptions\FormsException;
use Marko\Routing\Http\UploadedFile;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Session\Contracts\FlashDataInterface;
use Marko\Session\Exceptions\InvalidFlashDataException;
use Marko\Validation\Validation\ValidationErrors;

/**
 * Flashes a failed submission's validation errors and input for the next request.
 *
 * The only place that knows where flashed form state is stored: read it back
 * through FlashedFormStateInterface. The CSRF token, uploaded files and every
 * field in forms.never_flash are left out of the flashed input.
 */
readonly class FlashedFormStateWriter
{
    public const string ERRORS_KEY = 'forms.errors';

    public const string OLD_INPUT_KEY = 'forms.old_input';

    public function __construct(
        private FlashDataInterface $flashData,
        private FormsConfig $config,
    ) {}

    /**
     * @param array<string, mixed> $input The submitted input, usually $request->post()
     *
     * @throws FormsException|InvalidFlashDataException
     */
    public function flash(
        ValidationErrors $errors,
        array $input,
    ): void {
        $this->flashData->put(self::ERRORS_KEY, $errors->all());
        $this->flashData->put(self::OLD_INPUT_KEY, $this->flashableInput($input));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     *
     * @throws FormsException
     */
    private function flashableInput(
        array $input,
    ): array {
        unset($input[CsrfMiddleware::TOKEN_FIELD]);

        $input = $this->withoutUploadedFiles($input);

        foreach ($this->config->neverFlash() as $field) {
            $input = $this->withoutPath($input, explode('.', $field));
        }

        return $input;
    }

    /**
     * @param array<mixed> $input
     * @return array<mixed>
     */
    private function withoutUploadedFiles(
        array $input,
    ): array {
        foreach ($input as $key => $value) {
            if ($value instanceof UploadedFile) {
                unset($input[$key]);
            } elseif (is_array($value)) {
                $input[$key] = $this->withoutUploadedFiles($value);
            }
        }

        return $input;
    }

    /**
     * @param array<mixed> $input
     * @param list<string> $segments
     * @return array<mixed>
     */
    private function withoutPath(
        array $input,
        array $segments,
    ): array {
        $segment = array_shift($segments);

        if (!array_key_exists($segment, $input)) {
            return $input;
        }

        if ($segments === []) {
            unset($input[$segment]);
        } elseif (is_array($input[$segment])) {
            $input[$segment] = $this->withoutPath($input[$segment], $segments);
        }

        return $input;
    }
}
