<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PageCache\Command\StatusCommand;
use Marko\PageCache\Config\PageCacheConfig;
use Marko\Testing\Fake\FakeConfigRepository;

it('prints driver name and storage path in StatusCommand output', function (): void {
    $stream = fopen('php://memory', 'r+');
    $config = new PageCacheConfig(new FakeConfigRepository([
        'page-cache.driver' => 'file',
        'page-cache.path' => '/tmp/page-cache',
    ]));
    $command = new StatusCommand($config);
    $input = new Input(['bin/marko', 'page-cache:status']);
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $written = stream_get_contents($stream);
    fclose($stream);

    expect($result)->toBe(0)
        ->and($written)->toContain('Driver: file')
        ->and($written)->toContain('Path: /tmp/page-cache');
});
