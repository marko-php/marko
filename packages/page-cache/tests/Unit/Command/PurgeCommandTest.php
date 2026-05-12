<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Command\PurgeCommand;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

$fakePageCache = function (bool $purgeUrlResult = true, bool $purgeTagResult = true): PageCacheInterface {
    return new readonly class ($purgeUrlResult, $purgeTagResult) implements PageCacheInterface
    {
        public function __construct(
            private bool $purgeUrlResult,
            private bool $purgeTagResult,
        ) {}

        public function lookup(Request $request): ?Response
        {
            return null;
        }

        public function store(
            Request $request,
            Response $response,
            CachePolicy $policy,
        ): Response {
            return $response;
        }

        public function purgeUrl(string $url): bool
        {
            return $this->purgeUrlResult;
        }

        public function purgeTag(string $tag): bool
        {
            return $this->purgeTagResult;
        }

        public function clear(): bool
        {
            return true;
        }
    };
};

it('purges by URL when PurgeCommand is invoked with a URL argument', function () use ($fakePageCache): void {
    $stream = fopen('php://memory', 'r+');
    $command = new PurgeCommand($fakePageCache());
    $input = new Input(['bin/marko', 'page-cache:purge', 'https://example.com/page']);
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $written = stream_get_contents($stream);
    fclose($stream);

    expect($result)->toBe(0)
        ->and($written)->toContain("URL 'https://example.com/page' purged.");
});

it('purges by tag when PurgeCommand is invoked with --tag flag', function () use ($fakePageCache): void {
    $stream = fopen('php://memory', 'r+');
    $command = new PurgeCommand($fakePageCache());
    $input = new Input(['bin/marko', 'page-cache:purge', 'product-42', '--tag']);
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $written = stream_get_contents($stream);
    fclose($stream);

    expect($result)->toBe(0)
        ->and($written)->toContain("Tag 'product-42' purged.");
});

it('returns non-zero when PurgeCommand has no target argument', function () use ($fakePageCache): void {
    $stream = fopen('php://memory', 'r+');
    $command = new PurgeCommand($fakePageCache());
    $input = new Input(['bin/marko', 'page-cache:purge']);
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $written = stream_get_contents($stream);
    fclose($stream);

    expect($result)->toBe(1)
        ->and($written)->toContain('Error: No target specified.');
});
