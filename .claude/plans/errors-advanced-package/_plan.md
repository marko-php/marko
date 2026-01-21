# Plan: Advanced Error Handling Package (marko/errors-advanced)

## Created
2026-01-21

## Status
pending

## Objective
Create the `marko/errors-advanced` package providing an enhanced, beautiful error presentation layer for development environments. This package extends `marko/errors` with a production-quality HTML error formatter featuring syntax highlighting, formatted stack traces with source context, request/environment details, and dark mode support.

## Scope

### In Scope
- `PrettyHtmlFormatter` - Beautiful, modern HTML error page renderer for development
  - Professional dark/light mode toggle with CSS
  - Syntax highlighting for code snippets using built-in PHP tokenizer
  - Formatted stack trace with file navigation hints
  - Request details section (headers, query params, POST data)
  - Environment information (PHP version, installed extensions, server details)
  - Exception hierarchy and previous exceptions
  - Error severity indicators with visual styling
  - Responsive layout for mobile viewing
- `SyntaxHighlighter` - Code tokenization and styling
  - PHP syntax highlighting using `token_get_all()`
  - HTML escaping and span-based color coding
  - Support for inline highlighting context
- `RequestDataCollector` - Extracts and formats request information
  - Request headers, method, URI
  - Query parameters
  - POST data (with sensitive field masking)
  - Session/cookie information (sanitized)
  - Server variables
- `AdvancedErrorHandler` - Extends error handling with pretty formatter selection
  - Plugs into existing `ErrorHandlerInterface` contract
  - Falls back to `BasicHtmlFormatter` on rendering errors
  - Serves static assets (CSS) for error pages
- CSS styling for error pages (embedded in HTML, no external file dependencies)
- Integration with `marko/errors-simple` formatters as fallback
- Exception classes with helpful error messages
- Zero external dependencies (only uses PHP standard library)

### Out of Scope
- Template engine integration (Latte, Twig, etc.) - keep it self-contained
- JavaScript in error pages (except minimal dark mode toggle)
- Production error page customization (marko/errors-simple already handles this)
- Error tracking/reporting integration (separate from presentation)
- Custom error page themes beyond dark/light
- Logging integration (exists via error events)
- Dynamic code highlighting updates (static HTML only)

## Success Criteria
- [ ] `PrettyHtmlFormatter` renders beautiful, modern HTML error pages
- [ ] Syntax highlighting works for PHP code snippets with proper coloring
- [ ] Stack trace formatted with clickable file navigation (IDE opening, editor links)
- [ ] Request details captured and displayed safely (headers, params, data)
- [ ] Environment information displayed (PHP version, extensions, server)
- [ ] Dark mode toggle functional via CSS only (no JS dependencies)
- [ ] Fallback to BasicHtmlFormatter if pretty formatter fails
- [ ] Zero external dependencies beyond marko/errors and marko/core
- [ ] All code uses constructor property promotion, strict types, no final classes
- [ ] Comprehensive test coverage for formatters and data collectors
- [ ] Integration tests verify error handler chain works correctly
- [ ] All tests passing with minimum 80% coverage
- [ ] Code follows project standards

## Task Overview
| Task | Description | Depends On | Status |
|------|-------------|------------|--------|
| 001 | Package scaffolding (composer.json, module.php) | - | pending |
| 002 | Exception classes | 001 | pending |
| 003 | SyntaxHighlighter (PHP tokenization and coloring) | 002 | pending |
| 004 | RequestDataCollector (extract request info safely) | 002 | pending |
| 005 | PrettyHtmlFormatter core structure | 003, 004 | pending |
| 006 | PrettyHtmlFormatter dark/light mode CSS | 005 | pending |
| 007 | PrettyHtmlFormatter production vs development output | 005 | pending |
| 008 | PrettyHtmlFormatter stack trace formatting | 005, 003 | pending |
| 009 | PrettyHtmlFormatter request/environment display | 005, 004 | pending |
| 010 | AdvancedErrorHandler with fallback chain | 005, 007 | pending |
| 011 | Integration with marko/errors-simple | 010 | pending |
| 012 | Unit tests for SyntaxHighlighter | 003 | pending |
| 013 | Unit tests for RequestDataCollector | 004 | pending |
| 014 | Unit tests for PrettyHtmlFormatter | 005, 006, 007, 008, 009 | pending |
| 015 | Integration tests for error handler chain | 011 | pending |

## Architecture Notes

### Package Structure
```
packages/errors-advanced/
  src/
    AdvancedErrorHandler.php
    Exceptions/
      AdvancedErrorHandlerException.php
    Formatters/
      PrettyHtmlFormatter.php
    Highlighters/
      SyntaxHighlighter.php
    Collectors/
      RequestDataCollector.php
  tests/
    Unit/
      Highlighters/
        SyntaxHighlighterTest.php
      Collectors/
        RequestDataCollectorTest.php
      Formatters/
        PrettyHtmlFormatterTest.php
    Feature/
      AdvancedErrorHandlerTest.php
      ErrorHandlerIntegrationTest.php
  composer.json
  module.php
```

### Interface/Implementation Relationship
`marko/errors-advanced` builds on top of `marko/errors` and `marko/errors-simple`:
- Depends on `marko/errors` for interfaces and ErrorReport
- Compatible with `marko/errors-simple` formatters as fallback
- Provides alternative implementation of `ErrorHandlerInterface` or uses preference
- Does NOT replace `marko/errors-simple`; both packages coexist via preference selection

### SyntaxHighlighter Implementation
Uses PHP's `token_get_all()` to tokenize code:

```php
declare(strict_types=1);

namespace Marko\ErrorsAdvanced\Highlighters;

class SyntaxHighlighter
{
    public function highlight(string $code): string
    {
        $tokens = token_get_all('<?php ' . $code);
        $output = '';

        foreach ($tokens as $token) {
            if (is_array($token)) {
                $type = token_name($token[0]);
                $text = htmlspecialchars($token[1], ENT_QUOTES);
                $output .= "<span class=\"token-{$type}\">{$text}</span>";
            } else {
                $output .= htmlspecialchars($token, ENT_QUOTES);
            }
        }

        return $output;
    }
}
```

### RequestDataCollector Strategy
Safe extraction of request information with sensitive field masking:

```php
declare(strict_types=1);

namespace Marko\ErrorsAdvanced\Collectors;

class RequestDataCollector
{
    private const array SENSITIVE_KEYS = [
        'password', 'token', 'secret', 'apikey', 'api_key', 'auth',
        'credential', 'passwd', 'authorization',
    ];

    public function collect(): array
    {
        return [
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
            'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
            'headers' => $this->getHeaders(),
            'query' => $this->sanitize($_GET),
            'post' => $this->sanitize($_POST),
            'cookies' => $this->sanitizeCookies($_COOKIE),
            'server' => [
                'php_version' => phpversion(),
                'sapi' => php_sapi_name(),
                'memory' => ini_get('memory_limit'),
            ],
        ];
    }

    private function sanitize(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $lowercaseKey = strtolower((string) $key);
            $isSensitive = false;

            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($lowercaseKey, $sensitiveKey)) {
                    $isSensitive = true;
                    break;
                }
            }

            $result[$key] = $isSensitive ? '[REDACTED]' : $value;
        }

        return $result;
    }

    private function sanitizeCookies(array $cookies): array
    {
        return array_map(fn() => '[REDACTED]', $cookies);
    }

    private function getHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', substr($key, 5));
                $headers[$name] = $this->sanitizeHeaderValue($name, $value);
            }
        }
        return $headers;
    }

    private function sanitizeHeaderValue(string $name, string $value): string
    {
        $sensitiveHeaders = ['AUTHORIZATION', 'COOKIE', 'X-API-KEY'];
        return in_array(strtoupper($name), $sensitiveHeaders, true)
            ? '[REDACTED]'
            : $value;
    }
}
```

### Fallback Strategy
If `PrettyHtmlFormatter` fails during rendering:
1. Catch exception in `AdvancedErrorHandler`
2. Fall back to `BasicHtmlFormatter` from `marko/errors-simple`
3. Log formatter error for investigation
4. Display clean error page without rich formatting

### Integration with marko/errors-simple
Both formatters work within the same error handler contract:

```
ErrorReport
    ↓
SimpleErrorHandler (marko/errors-simple)
    ↓
    ├─ TextFormatter (CLI)
    └─ BasicHtmlFormatter (Web)

// Or with preference:

ErrorReport
    ↓
AdvancedErrorHandler (marko/errors-advanced) #[Preference]
    ↓
    ├─ TextFormatter (CLI)
    └─ PrettyHtmlFormatter (Web) → fallback to BasicHtmlFormatter
```

Module can declare preference to use advanced handler:
```php
// module.php in marko/errors-advanced
return [
    'bindings' => [
        ErrorHandlerInterface::class => AdvancedErrorHandler::class,
    ],
];
```

### Environment-Based Behavior
- **Development**: Full details, syntax highlighting, request data
- **Production**: Generic error message (same as errors-simple), no details

### HTML Structure
Self-contained error page with:
- Header with exception type and message
- File/line indicator with syntax highlighted code snippet
- Stack trace with file navigation
- Request information (headers, params, etc.)
- Environment details
- Dark mode toggle (CSS-based via `prefers-color-scheme` media query)

CSS includes:
- Base styles for layout and typography
- Syntax highlighting colors (light and dark variants)
- Responsive mobile layout
- Dark mode styles via media query and data attribute

### Module Bindings
```php
// packages/errors-advanced/module.php
declare(strict_types=1);

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsAdvanced\AdvancedErrorHandler;
use Marko\ErrorsAdvanced\Collectors\RequestDataCollector;
use Marko\ErrorsAdvanced\Highlighters\SyntaxHighlighter;

return [
    'enabled' => true,
    'bindings' => [
        SyntaxHighlighter::class => SyntaxHighlighter::class,
        RequestDataCollector::class => RequestDataCollector::class,
        ErrorHandlerInterface::class => AdvancedErrorHandler::class,
    ],
];
```

## Risks & Mitigations

| Risk | Mitigation |
|------|------------|
| **HTML rendering fails during error handling** | Wrap PrettyHtmlFormatter in try/catch, fall back to BasicHtmlFormatter |
| **Syntax highlighting contains errors** | Test extensively with various PHP code patterns; degrade gracefully if tokenization fails |
| **Request data collection leaks sensitive info** | Implement comprehensive sanitization; mask passwords, tokens, API keys; test with common field names |
| **CSS becomes too large** | Keep CSS minimal; use short class names; only include necessary styles; no external fonts |
| **Memory issues during HTML generation** | Limit code snippet context; process stack trace iteratively; avoid buffering entire page |
| **Performance impact of syntax highlighting** | Tokenize only visible code snippets; cache token colors; profile before shipping |
| **Dark mode preference not detected** | Fall back to light mode on detection failure; provide CSS-only toggle |
| **IDE integration links broken** | Document editor-specific URL schemes (VS Code, PhpStorm); make them optional |
| **Formatter fails if error occurs during error handling** | Log to fallback text output; never throw in catch block; defensive programming throughout |
