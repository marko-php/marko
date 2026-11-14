<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use Marko\Core\Contracts\UploadedFileInterface;
use Marko\Core\Exceptions\MarkoException;
use Marko\Validation\Contracts\RuleInterface;

/**
 * Base for rules that inspect an uploaded file.
 *
 * Rejects anything that is not an UploadedFileInterface, and any upload that failed or was
 * already moved, before the subclass inspects the file. That ordering matters: reading the
 * contents of a failed upload (e.g. to sniff its MIME type) throws.
 */
abstract readonly class AbstractFileRule implements RuleInterface
{
    /**
     * @throws MarkoException
     */
    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        if (
            !$value instanceof UploadedFileInterface
            || !$value->isValid()
        ) {
            return false;
        }

        return $this->passesFile($value);
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        if (!$value instanceof UploadedFileInterface) {
            return "The $field field must be a file.";
        }

        if ($value->isMoved()) {
            return "The $field file has already been moved.";
        }

        if (in_array($value->error(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return "The $field file is larger than the server allows.";
        }

        if (!$value->isValid()) {
            return "The $field file failed to upload.";
        }

        return $this->fileMessage($field);
    }

    /**
     * Inspect a successfully uploaded file that has not been moved.
     *
     * @throws MarkoException when the file cannot be read
     */
    abstract protected function passesFile(
        UploadedFileInterface $file,
    ): bool;

    abstract protected function fileMessage(
        string $field,
    ): string;
}
