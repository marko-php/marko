# Task 021: GD re-encodes to PNG and flattens alpha

**Status**: complete
**Depends on**: [none]
**Retry count**: 0

## Description
`GdImageProcessor::resize()` and `crop()` have three defects:
1. They ALWAYS write the output as PNG (`uniqid(...) . '.png'` then `imagepng($canvas, ...)`), regardless of the source format — so resizing a JPEG silently returns a PNG.
2. The `imagecreatetruecolor()` canvas is created without `imagealphablending(false)` + `imagesavealpha(true)`, and no transparent fill, so transparent PNG/WebP sources are composited onto an opaque black background — transparency is lost.
3. The `imagepng()` (and any other encode) return value is unchecked, so an encode failure returns a path to a non-image silently.

Preserve the source format on output, enable alpha preservation for PNG/WebP, and check encode return values (throwing `GdProcessingException` loudly on failure).

## Description-note
Loud errors plus "do what the caller expects": a resize must not change the format or destroy transparency, and a failed encode must not silently hand back a bogus path. The `convert()` method already encodes per-format via a `match` and `normalizeFormat()` — reuse that machinery so `resize()`/`crop()` honor the detected source format.

## Context
- Related files:
  - `/Users/markshust/Sites/marko/packages/media-gd/src/Driver/GdImageProcessor.php` (`resize()` ~31-55, `crop()` ~62-78 — both hardcode `.png` + `imagepng(...)` with no return check and no alpha setup; `convert()` ~84-97 — has the per-format `match($extension) { 'jpeg' => imagejpeg, 'png' => imagepng, 'webp' => imagewebp, 'gif' => imagegif }`; `loadImage()` ~104-130 uses `imagecreatefromstring`; `normalizeFormat()` ~133+ maps/validates `jpeg|png|gif|webp`)
  - Tests: `/Users/markshust/Sites/marko/packages/media-gd/tests/` (locate the existing GD processor test; tests should create real small images via GD in a temp dir)
- Verified findings (source-confirmed):
  - `resize()`: builds a `imagecreatetruecolor($width,$height)` canvas, `imagecopyresampled(...)`, then `$outputPath = sys_get_temp_dir().'/'.uniqid('marko-gd-',true).'.png'; imagepng($canvas, $outputPath); return $outputPath;` — format hardcoded to png, no alpha flags, return unchecked.
  - `crop()`: identical pattern with `imagecopy(...)`.
  - `convert()` already demonstrates the correct per-format encode `match` and reaches it via `normalizeFormat($format)`.
  - `loadImage()` returns a `GdImage` from `imagecreatefromstring()` but does NOT currently report the detected format — detection will need `getimagesize()`/`exif_imagetype()` (or `image_type_to_extension`) on the source path inside `resize()`/`crop()`.
- Patterns to follow:
  - Detect the source format from the input path (e.g. `getimagesize($imagePath)[2]` → `IMAGETYPE_*`, mapped to an extension via `image_type_to_extension(..., false)` then normalized through the existing `normalizeFormat()` vocabulary). If detection fails, throw `GdProcessingException` (loud) — do not default-to-png silently.
  - Build the output path with the detected extension, and encode via the SAME per-format `match` `convert()` uses (extract a small private `encode(GdImage $image, string $extension, string $outputPath): void` helper that both `convert()` and `resize()`/`crop()` call, so there is one encode path — DRY).
  - For PNG and WebP canvases, before copying: `imagealphablending($canvas, false); imagesavealpha($canvas, true);` and fill with a transparent color (`imagecolorallocatealpha($canvas, 0,0,0,127)` via `imagefilledrectangle`) so transparency survives the resample/crop.
  - Check the encode boolean: `if (imagepng(...) === false) throw GdProcessingException::processingFailed(...)` (use the existing `processingFailed`/`unsupportedFormat` factories; confirm signatures). Apply to ALL encode calls in the shared helper.
  - Do NOT change the public method signatures (`resize`/`crop`/`thumbnail`/`convert` keep their parameters). `thumbnail()` already delegates to `resize()`, so it inherits the fix.

## Requirements (Test Descriptions)
- [x] `it outputs a JPEG when resizing a JPEG source`
- [x] `it outputs a PNG when resizing a PNG source`
- [x] `it preserves transparency when resizing a transparent PNG`
- [x] `it preserves the source format when cropping`
- [x] `it throws GdProcessingException when encoding fails`

## Acceptance Criteria
- Resizing/cropping preserves the source image format (JPEG stays JPEG, PNG stays PNG).
- A transparent PNG/WebP retains its transparency after resize/crop (no black flattening).
- An encode failure surfaces a `GdProcessingException` (no silent bogus path).
- All requirements have passing tests
- Code follows code standards
- No decrease in test coverage

## Implementation Notes
- Added `detectFormat(string $imagePath): string` private method using `exif_imagetype()` + `image_type_to_extension()` to detect source format; throws `GdProcessingException` loudly if detection fails.
- Added `prepareCanvasAlpha(GdImage $canvas, string $extension): void` private method that sets `imagealphablending(false)` + `imagesavealpha(true)` and fills with a transparent color for PNG/WebP canvases.
- Extracted `encode(GdImage $image, string $extension, string $outputPath): void` as a shared `protected` method used by `resize()`, `crop()`, and `convert()` — single encode path, checks return value and throws `GdProcessingException::processingFailed('encode', ...)` on failure.
- Made `encode()` `protected` (not `private`) to allow test subclassing for encode failure simulation.
- Both `resize()` and `crop()` now detect format, prepare alpha (if needed), and encode via the shared method.
- `thumbnail()` inherits the fixes automatically via `resize()`.
