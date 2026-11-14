<?php

declare(strict_types=1);

namespace Marko\Validation\Rules;

use InvalidArgumentException;
use Marko\Core\Contracts\UploadedFileInterface;
use Marko\Core\Exceptions\MarkoException;

/**
 * The extension of the MIME type sniffed from the file contents is one of the listed extensions.
 *
 * The client filename is never consulted: `photo.png` that is really text fails `mimes:png`.
 */
readonly class Mimes extends AbstractFileRule
{
    /** Spellings that name the same type as the extension guessExtension() reports. */
    private const array ALIASES = ['jpeg' => 'jpg', 'jpe' => 'jpg', 'tiff' => 'tif', 'htm' => 'html'];

    /**
     * @var array<string>
     */
    private array $extensions;

    /**
     * @throws InvalidArgumentException when no extensions are given
     */
    public function __construct(
        string ...$extensions,
    ) {
        $normalized = array_values(array_filter(array_map(self::normalize(...), $extensions)));

        if ($normalized === []) {
            throw new InvalidArgumentException(
                'The mimes rule needs at least one extension, e.g. mimes:jpg,png,pdf',
            );
        }

        $this->extensions = $normalized;
    }

    /**
     * @throws MarkoException
     */
    protected function passesFile(
        UploadedFileInterface $file,
    ): bool {
        $extension = $file->guessExtension();

        return $extension !== null
            && in_array(self::normalize($extension), $this->extensions, true);
    }

    protected function fileMessage(
        string $field,
    ): string {
        return "The $field field must be a file of type: " . implode(', ', $this->extensions) . '.';
    }

    private static function normalize(
        string $extension,
    ): string {
        $extension = strtolower(ltrim(trim($extension), '.'));

        return self::ALIASES[$extension] ?? $extension;
    }
}
