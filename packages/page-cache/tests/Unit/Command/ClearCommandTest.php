<?php

declare(strict_types=1);

use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\PageCache\CachePolicy;
use Marko\PageCache\Command\ClearCommand;
use Marko\PageCache\Contracts\PageCacheInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

$fakePageCache = function (bool $clearResult): PageCacheInterface {
    return new readonly class ($clearResult) implements PageCacheInterface
    {
        public function __construct(private bool $clearResult) {}

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
            return true;
        }

        public function purgeTag(string $tag): bool
        {
            return true;
        }

        public function clear(): bool
        {
            return $this->clearResult;
        }
    };
};

it('prints success when ClearCommand clears the cache', function () use ($fakePageCache): void {
    $stream = fopen('php://memory', 'r+');
    $command = new ClearCommand($fakePageCache(true));
    $input = new Input(['bin/marko', 'page-cache:clear']);
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $written = stream_get_contents($stream);
    fclose($stream);

    expect($result)->toBe(0)
        ->and($written)->toContain('Page cache cleared successfully.');
});

it('prints failure and returns non-zero when ClearCommand fails to clear', function () use ($fakePageCache): void {
    $stream = fopen('php://memory', 'r+');
    $command = new ClearCommand($fakePageCache(false));
    $input = new Input(['bin/marko', 'page-cache:clear']);
    $output = new Output($stream);

    $result = $command->execute($input, $output);

    rewind($stream);
    $written = stream_get_contents($stream);
    fclose($stream);

    expect($result)->toBe(1)
        ->and($written)->toContain('Failed to clear the page cache.');
});
