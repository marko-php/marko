<?php

declare(strict_types=1);

use Marko\Queue\JobIdGenerator;

describe('JobIdGenerator', function (): void {
    it('generates RFC 4122 version 4 UUIDs', function (): void {
        $id = new JobIdGenerator()->generate();

        expect($id)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');
    });

    it('generates a different id on every call', function (): void {
        $generator = new JobIdGenerator();
        $ids = array_map(fn (): string => $generator->generate(), range(1, 100));

        expect(array_unique($ids))->toHaveCount(100);
    });
});
