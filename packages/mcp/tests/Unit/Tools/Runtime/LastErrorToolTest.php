<?php

declare(strict_types=1);

use Marko\Mcp\Tools\Runtime\Contracts\ErrorTrackerInterface;
use Marko\Mcp\Tools\Runtime\LastErrorTool;

function makeErrorTracker(?array $error): ErrorTrackerInterface
{
    return new class ($error) implements ErrorTrackerInterface
    {
        public function __construct(private readonly ?array $error) {}

        public function lastError(): ?array
        {
            return $this->error;
        }
    };
}

it('registers last_error tool returning the most recent error with stack trace', function (): void {
    $tracker = makeErrorTracker([
        'message' => 'Call to undefined method Foo::bar()',
        'file' => '/var/www/src/Foo.php',
        'line' => 42,
        'trace' => "#0 /var/www/src/Bar.php(10): Foo->bar()\n#1 {main}",
        'timestamp' => 1700000000,
    ]);

    $definition = LastErrorTool::definition($tracker);

    expect($definition->name)->toBe('last_error');

    $result = $definition->handler->handle([]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('Call to undefined method Foo::bar()')
        ->and($text)->toContain('/var/www/src/Foo.php')
        ->and($text)->toContain('42')
        ->and($text)->toContain('#0 /var/www/src/Bar.php');
});

it('returns a no error message when no error has been recorded', function (): void {
    $tracker = makeErrorTracker(null);

    $result = LastErrorTool::definition($tracker)->handler->handle([]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain('No error');
});
