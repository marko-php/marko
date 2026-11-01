<?php

declare(strict_types=1);

use Marko\Core\Container\Container;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Tests\Fixtures\UniqueTestJob;
use Marko\Queue\UniqueJobLocks;

describe('marko/cache is optional for marko/queue', function (): void {
    it('lists marko/cache as a suggestion, not a requirement', function (): void {
        $composer = json_decode(
            (string) file_get_contents(dirname(__DIR__) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        expect($composer['require'])->not->toHaveKey('marko/cache')
            ->and($composer['suggest'])->toHaveKey('marko/cache')
            ->and($composer['require-dev'])->toHaveKey('marko/cache');
    });

    it('throws a clear exception telling you to install marko/cache when a unique job is pushed without it', function (): void {
        $locks = new class (new Container()) extends UniqueJobLocks
        {
            protected function lockContractInstalled(): bool
            {
                return false;
            }
        };

        try {
            $locks->acquire(new UniqueTestJob());
            $this->fail('Taking a unique-job lock without marko/cache must throw.');
        } catch (QueueException $e) {
            expect($e->getMessage())->toContain('marko/cache is not installed')
                ->and($e->getMessage())->toContain(UniqueTestJob::class)
                ->and($e->getSuggestion())->toContain('composer require marko/cache')
                ->and($e->getSuggestion())->toContain('marko/cache-redis');
        }
    });
});
