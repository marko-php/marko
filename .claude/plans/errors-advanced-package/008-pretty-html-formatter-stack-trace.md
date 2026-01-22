# Task 008: PrettyHtmlFormatter Stack Trace Formatting

**Status**: completed
**Depends on**: 005, 003
**Retry count**: 0

## Description
Format stack trace with syntax highlighting and file navigation.

## Context
- Uses SyntaxHighlighter for code snippets
- File navigation hints for IDE opening
- Previous exceptions included

## Requirements (Test Descriptions)
- [x] `it formats stack trace entries`
- [x] `it shows file and line for each frame`
- [x] `it highlights code at each frame`
- [x] `it shows function/method name`
- [x] `it handles previous exceptions`
- [x] `it limits context lines per frame`

## Acceptance Criteria
- All requirements have passing tests
- Stack trace is readable and navigable
- Code context is helpful

## Implementation Notes
Implemented stack trace formatting in PrettyHtmlFormatter with:

1. **formatStackTrace()** - Iterates over trace array and formats each frame
2. **formatStackFrame()** - Renders individual stack frame with:
   - frame-function: Shows the function/method name (ClassName->method() or function())
   - frame-location: Shows file:line with proper HTML escaping
   - frame-code: Syntax-highlighted code snippet from the file
3. **formatFunctionName()** - Formats function name handling class methods with type indicator
4. **formatPreviousException()** - Displays chained exceptions with their message, location, and code
5. **contextLines parameter** - Constructor parameter to limit context lines per frame (passed to SyntaxHighlighter)
