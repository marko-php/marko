<?php

declare(strict_types=1);

namespace Marko\Media\Image;

use Marko\Media\Exceptions\ImageFormatException;

/**
 * Detects a raster image's format from its magic bytes, without decoding it.
 *
 * Image processors run this before handing a file to a decoder so that the
 * format allowlist is enforced on what the file actually is — not on what a
 * library such as ImageMagick decides it is after parsing (and possibly
 * executing) it. Returned names match ImageMagick coder names.
 */
class ImageFormatSniffer
{
    public const string JPEG = 'JPEG';

    public const string PNG = 'PNG';

    public const string GIF = 'GIF';

    public const string WEBP = 'WEBP';

    public const string AVIF = 'AVIF';

    public const string HEIC = 'HEIC';

    public const string TIFF = 'TIFF';

    public const string BMP = 'BMP';

    private const int HEADER_BYTES = 64;

    /** @var array<string> */
    private const array AVIF_BRANDS = ['avif', 'avis'];

    /** @var array<string> */
    private const array HEIC_BRANDS = ['heic', 'heix', 'heim', 'heis', 'hevc', 'hevx'];

    /**
     * Return the detected format name (one of the class constants).
     *
     * @throws ImageFormatException
     */
    public function sniff(
        string $path,
    ): string {
        $this->assertSafePath($path);

        if (
            !is_file($path)
            || !is_readable($path)
        ) {
            throw ImageFormatException::notReadable($path);
        }

        $header = file_get_contents($path, false, null, 0, self::HEADER_BYTES);

        if ($header === false) {
            throw ImageFormatException::notReadable($path);
        }

        return $this->detect($header) ?? throw ImageFormatException::unrecognisedFormat($path);
    }

    /**
     * Reject paths that a decoder or PHP could interpret as something other
     * than a plain local file: ImageMagick coder prefixes ("msl:", "url:",
     * "PNG:") and PHP stream wrappers ("phar://", "http://", "data:").
     *
     * @throws ImageFormatException
     */
    public function assertSafePath(
        string $path,
    ): void {
        if ($path === '') {
            throw ImageFormatException::unsafePath($path, 'path is empty');
        }

        if (str_contains($path, "\0")) {
            throw ImageFormatException::unsafePath($path, 'path contains a NUL byte');
        }

        if (
            preg_match('/^[a-z0-9+.\-]+:/i', $path) === 1
            && !$this->isWindowsDrivePath($path)
        ) {
            throw ImageFormatException::unsafePath($path, 'path starts with a coder prefix or stream wrapper');
        }
    }

    private function isWindowsDrivePath(
        string $path,
    ): bool {
        return PHP_OS_FAMILY === 'Windows'
            && preg_match('/^[a-z]:[\\\\\/]/i', $path) === 1;
    }

    private function detect(
        string $header,
    ): ?string {
        return match (true) {
            str_starts_with($header, "\xFF\xD8\xFF") => self::JPEG,
            str_starts_with($header, "\x89PNG\r\n\x1A\n") => self::PNG,
            str_starts_with($header, 'GIF87a'), str_starts_with($header, 'GIF89a') => self::GIF,
            str_starts_with($header, 'RIFF')
                && substr($header, 8, 4) === 'WEBP' => self::WEBP,
            str_starts_with($header, "II*\x00"), str_starts_with($header, "MM\x00*") => self::TIFF,
            str_starts_with($header, 'BM')
                && strlen($header) >= 14 => self::BMP,
            substr($header, 4, 4) === 'ftyp' => $this->detectIsoBaseMedia($header),
            default => null,
        };
    }

    /**
     * AVIF and HEIC are ISO base media files; identify them by the major and
     * compatible brands of the leading "ftyp" box.
     */
    private function detectIsoBaseMedia(
        string $header,
    ): ?string {
        $boxSize = unpack('N', substr($header, 0, 4));
        $boxSize = is_array($boxSize) ? min((int) $boxSize[1], strlen($header)) : 0;

        if ($boxSize < 16) {
            return null;
        }

        // Major brand at offset 8, minor version at 12, compatible brands from 16.
        $brands = [substr($header, 8, 4)];

        for ($offset = 16; $offset + 4 <= $boxSize; $offset += 4) {
            $brands[] = substr($header, $offset, 4);
        }

        if (array_intersect($brands, self::AVIF_BRANDS) !== []) {
            return self::AVIF;
        }

        if (array_intersect($brands, self::HEIC_BRANDS) !== []) {
            return self::HEIC;
        }

        return null;
    }
}
