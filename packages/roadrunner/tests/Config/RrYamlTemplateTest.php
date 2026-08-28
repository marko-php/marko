<?php

declare(strict_types=1);

namespace Marko\Roadrunner\Tests\Config;

use Marko\Roadrunner\Config\RrYamlTemplate;

describe('RrYamlTemplate', function (): void {
    it('ships a default rr yaml pointing at the packaged worker', function (): void {
        $yaml = RrYamlTemplate::render('/app');

        expect($yaml)->toContain('version: "3"')
            ->and($yaml)->toContain('command: "php vendor/marko/roadrunner/worker.php"')
            ->and($yaml)->toContain('relay: pipes');
    });

    it('configures static file serving from the public directory', function (): void {
        $yaml = RrYamlTemplate::render('/app');

        expect($yaml)->toContain('static:')
            ->and($yaml)->toContain('dir: public')
            ->and($yaml)->toContain('forbid:');
    });

    it('configures a max jobs worker recycle limit', function (): void {
        $yaml = RrYamlTemplate::render('/app');

        expect($yaml)->toContain('max_jobs: 64')
            ->and($yaml)->toContain('# max_jobs recycles each worker after N requests');
    });

    it('configures a worker memory ceiling', function (): void {
        $yaml = RrYamlTemplate::render('/app');

        expect($yaml)->toContain('max_worker_memory: 128')
            ->and($yaml)->toContain('# Kill and replace a worker once it grows past this memory ceiling');
    });

    it('passes the base path to the worker through the server environment', function (): void {
        $yaml = RrYamlTemplate::render('/srv/my-app');

        expect($yaml)->toContain('env:')
            ->and($yaml)->toContain('MARKO_BASE_PATH: "/srv/my-app"');
    });
});
