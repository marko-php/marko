<?php

declare(strict_types=1);

use Marko\Database\ReadWrite\Config\ReadWriteConnectionConfig;
use Marko\Database\ReadWrite\Exceptions\ReadWriteConfigException;

$validWrite = [
    'driver' => 'pgsql',
    'host' => 'primary.db',
    'port' => 5432,
    'database' => 'app',
    'username' => 'user',
    'password' => 'pass',
];

$validReads = [
    ['driver' => 'pgsql', 'host' => 'replica1.db', 'port' => 5432, 'database' => 'app', 'username' => 'user', 'password' => 'pass'],
    ['driver' => 'pgsql', 'host' => 'replica2.db', 'port' => 5432, 'database' => 'app', 'username' => 'user', 'password' => 'pass'],
];

$validWeightedReads = [
    ['driver' => 'pgsql', 'host' => 'replica1.db', 'port' => 5432, 'database' => 'app', 'username' => 'user', 'password' => 'pass', 'weight' => 1],
    ['driver' => 'pgsql', 'host' => 'replica2.db', 'port' => 5432, 'database' => 'app', 'username' => 'user', 'password' => 'pass', 'weight' => 3],
];

describe('ReadWriteConnectionConfig', function () use ($validWrite, $validReads, $validWeightedReads): void {
    it(
        'builds ReadWriteConnectionConfig from a valid array with random strategy',
        function () use ($validWrite, $validReads): void {
            $config = ReadWriteConnectionConfig::fromArray([
                'connections' => [
                    'write' => $validWrite,
                    'read' => $validReads,
                    'read_strategy' => 'random',
                ],
            ]);

            expect($config->write)->toBe($validWrite)
                ->and($config->reads)->toBe($validReads)
                ->and($config->readStrategy)->toBe('random');
        },
    );

    it('defaults read_strategy to random when not specified', function () use ($validWrite, $validReads): void {
        $config = ReadWriteConnectionConfig::fromArray([
            'connections' => [
                'write' => $validWrite,
                'read' => $validReads,
            ],
        ]);

        expect($config->readStrategy)->toBe('random');
    });

    it(
        'builds ReadWriteConnectionConfig from a valid array with weighted strategy',
        function () use ($validWrite, $validWeightedReads): void {
            $config = ReadWriteConnectionConfig::fromArray([
                'connections' => [
                    'write' => $validWrite,
                    'read' => $validWeightedReads,
                    'read_strategy' => 'weighted',
                ],
            ]);

            expect($config->write)->toBe($validWrite)
                ->and($config->reads)->toBe($validWeightedReads)
                ->and($config->readStrategy)->toBe('weighted');
        },
    );

    it('throws ReadWriteConfigException when connections key is missing', function (): void {
        expect(fn () => ReadWriteConnectionConfig::fromArray([]))->toThrow(ReadWriteConfigException::class);
    });

    it('throws ReadWriteConfigException when write connection is missing', function () use ($validReads): void {
        expect(fn () => ReadWriteConnectionConfig::fromArray([
            'connections' => [
                'read' => $validReads,
            ],
        ]))->toThrow(ReadWriteConfigException::class);
    });

    it('throws ReadWriteConfigException when read connections array is empty', function () use ($validWrite): void {
        expect(fn () => ReadWriteConnectionConfig::fromArray([
            'connections' => [
                'write' => $validWrite,
                'read' => [],
            ],
        ]))->toThrow(ReadWriteConfigException::class);
    });

    it(
        'throws ReadWriteConfigException when read_strategy is unknown',
        function () use ($validWrite, $validReads): void {
            expect(fn () => ReadWriteConnectionConfig::fromArray([
                'connections' => [
                    'write' => $validWrite,
                    'read' => $validReads,
                    'read_strategy' => 'round_robin',
                ],
            ]))->toThrow(ReadWriteConfigException::class);
        },
    );

    it(
        'throws ReadWriteConfigException when weight is zero in weighted strategy',
        function () use ($validWrite): void {
            expect(fn () => ReadWriteConnectionConfig::fromArray([
                'connections' => [
                    'write' => $validWrite,
                    'read' => [
                        ['driver' => 'pgsql', 'host' => 'replica1.db', 'weight' => 0],
                    ],
                    'read_strategy' => 'weighted',
                ],
            ]))->toThrow(ReadWriteConfigException::class);
        },
    );

    it(
        'throws ReadWriteConfigException when weight is negative in weighted strategy',
        function () use ($validWrite): void {
            expect(fn () => ReadWriteConnectionConfig::fromArray([
                'connections' => [
                    'write' => $validWrite,
                    'read' => [
                        ['driver' => 'pgsql', 'host' => 'replica1.db', 'weight' => -1],
                    ],
                    'read_strategy' => 'weighted',
                ],
            ]))->toThrow(ReadWriteConfigException::class);
        },
    );

    it(
        'throws ReadWriteConfigException when weight is non-integer in weighted strategy',
        function () use ($validWrite): void {
            expect(fn () => ReadWriteConnectionConfig::fromArray([
                'connections' => [
                    'write' => $validWrite,
                    'read' => [
                        ['driver' => 'pgsql', 'host' => 'replica1.db', 'weight' => 1.5],
                    ],
                    'read_strategy' => 'weighted',
                ],
            ]))->toThrow(ReadWriteConfigException::class);
        },
    );

    it('ignores weight validation when using random strategy', function () use ($validWrite): void {
        $readsWithBadWeights = [
            ['driver' => 'pgsql', 'host' => 'replica1.db', 'weight' => -5],
            ['driver' => 'pgsql', 'host' => 'replica2.db', 'weight' => 0],
        ];

        $config = ReadWriteConnectionConfig::fromArray([
            'connections' => [
                'write' => $validWrite,
                'read' => $readsWithBadWeights,
                'read_strategy' => 'random',
            ],
        ]);

        expect($config->readStrategy)->toBe('random');
    });
});
