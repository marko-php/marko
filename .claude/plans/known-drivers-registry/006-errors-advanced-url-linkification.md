# Task 006: Render context/suggestion + URL linkification in marko/errors-advanced

**Status**: pending
**Depends on**: none
**Retry count**: 0

## Description
`marko/errors-advanced` renders exceptions as a pretty HTML page. Two gaps must be addressed:

1. **`PrettyHtmlFormatter::formatDevelopment` does NOT currently render `$report->context` or `$report->suggestion`** — only `$report->message` is rendered. This means all the carefully-crafted context and suggestion text in `MarkoException` subclasses (including the new docs URLs added by the known-drivers refactor in tasks 003 and 008-024) is silently dropped from the HTML output. Confirm by reading `packages/errors-advanced/src/PrettyHtmlFormatter.php` lines 58-89.
2. URLs in exception text render as plain text (not clickable) because the rendering uses a generic `htmlspecialchars` escape via the private `escape()` method.

After this task: `context` and `suggestion` are rendered in the HTML output (each as its own paragraph block, positioned after the message), AND URLs in message/context/suggestion are auto-detected and rendered as `<a href="..." target="_blank" rel="noopener noreferrer">` links. Applied uniformly — this is a generic rendering improvement, not NoDriverException-specific.

## Context
- Files to modify:
  - `packages/errors-advanced/src/PrettyHtmlFormatter.php`:
    - `formatDevelopment()` (lines 58-89): add rendering of `$report->context` (when non-empty) and `$report->suggestion` (when non-empty), each in its own paragraph (e.g., `<p class="context">` and `<p class="suggestion">`, with `white-space: pre-wrap` so the multi-line installation text in NoDriverException renders correctly)
    - `escape()` (line 134-138): keep as-is for non-user-facing values (filenames, request data) but introduce a new `escapeAndLinkifyUrls()` private method for user-facing exception text (message, context, suggestion)
    - `getEmbeddedCss()` (lines 92-115): add `.context` and `.suggestion` rules (consider `white-space: pre-wrap` since suggestion text from NoDriverException contains literal `\n` separators)
- New test file: `packages/errors-advanced/tests/UrlLinkificationTest.php`
- Existing tests in `packages/errors-advanced/tests/` may need updates if they assert on output HTML structure

**URL detection pattern (conservative):**
- Match `https?://` followed by non-whitespace, non-`<`, non-`"`, non-`'` characters
- Stop at whitespace, `<`, `>`, `"`, `'`, end of string
- Recommended regex: `/(https?:\/\/[^\s<>"\']+)/`
- Trim trailing punctuation that's unlikely to be part of a URL: `.`, `,`, `;`, `:`, `!`, `?`, `)`, `]`

**Implementation shape (private helper):**
```php
private function escapeAndLinkifyUrls(string $value): string
{
    $pattern = '/(https?:\/\/[^\s<>"\']+)/';
    $parts = preg_split($pattern, $value, -1, PREG_SPLIT_DELIM_CAPTURE);

    $output = '';
    foreach ($parts as $i => $part) {
        if ($i % 2 === 0) {
            $output .= htmlspecialchars($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        } else {
            // Trim trailing punctuation
            $trailing = '';
            while (strlen($part) > 0 && in_array($part[-1], ['.', ',', ';', ':', '!', '?', ')', ']'], true)) {
                $trailing = $part[-1] . $trailing;
                $part = substr($part, 0, -1);
            }
            $escapedUrl = htmlspecialchars($part, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $output .= "<a href=\"{$escapedUrl}\" target=\"_blank\" rel=\"noopener noreferrer\">{$escapedUrl}</a>";
            $output .= htmlspecialchars($trailing, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    return $output;
}
```

Existing call sites that use `htmlspecialchars()` directly on user-facing exception text should be updated to use this new helper.

## Requirements (Test Descriptions)
- [ ] `it renders the context field in the HTML output when non-empty`
- [ ] `it renders the suggestion field in the HTML output when non-empty`
- [ ] `it omits empty context and suggestion blocks (does not render empty paragraphs)`
- [ ] `it preserves newlines in the suggestion text via white-space pre-wrap`
- [ ] `it renders http URLs as anchor tags with target blank and noopener noreferrer`
- [ ] `it renders https URLs as anchor tags`
- [ ] `it htmlspecialchars-escapes non-URL text portions`
- [ ] `it preserves URLs inside mixed text correctly`
- [ ] `it does not linkify text that looks URL-ish but lacks a protocol (e.g., www.example.com without http)`
- [ ] `it trims trailing punctuation from URL matches (period, comma, etc.)`
- [ ] `it escapes HTML special characters within the URL itself (defense against malformed input)`
- [ ] `it does not double-escape when the input has no URLs`
- [ ] `it linkifies URLs that appear in the suggestion field (NoDriverException docs URLs)`

## Acceptance Criteria
- `formatDevelopment()` renders `context` and `suggestion` fields when non-empty
- CSS includes `white-space: pre-wrap` (or equivalent) for `.suggestion` so multi-line text renders correctly
- New `escapeAndLinkifyUrls()` private method added to `PrettyHtmlFormatter`
- Replaces the `escape()` call on `$report->message` (line 61); also used for the new context and suggestion rendering
- The original `escape()` method is retained for non-user-facing values (filenames, request data) — no security regression for those callsites
- All anchor tags include both `target="_blank"` AND `rel="noopener noreferrer"` (security-required)
- Existing errors-advanced tests still pass (regression check); any tests that asserted on the old HTML structure are updated to match
- New test file covers all requirements above
- Code follows code standards (typed params/returns, `@throws` if applicable)
