<?php

declare(strict_types=1);

use Marko\Mcp\Tools\Runtime\AppInfoTool;

it('registers app_info tool returning PHP Marko DB engine and package versions', function (): void {
    $definition = AppInfoTool::definition();

    expect($definition->name)->toBe('app_info');

    $result = $definition->handler->handle([]);
    $text = $result['content'][0]['text'];

    expect($text)->toContain(PHP_VERSION);
});
