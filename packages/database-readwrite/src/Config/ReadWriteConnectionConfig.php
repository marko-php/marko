<?php

declare(strict_types=1);

namespace Marko\Database\ReadWrite\Config;

use Marko\Database\ReadWrite\Exceptions\ReadWriteConfigException;

readonly class ReadWriteConnectionConfig
{
    private const string STRATEGY_RANDOM = 'random';
    private const string STRATEGY_WEIGHTED = 'weighted';

    public function __construct(
        public array $write,
        public array $reads,
        public string $readStrategy,
    ) {}

    /**
     * @throws ReadWriteConfigException
     */
    public static function fromArray(array $config): self
    {
        if (!isset($config['connections']) || !is_array($config['connections'])) {
            throw ReadWriteConfigException::missingConnectionsKey();
        }

        $connections = $config['connections'];

        if (!isset($connections['write']) || !is_array($connections['write']) || empty($connections['write'])) {
            throw ReadWriteConfigException::missingWriteConnection();
        }

        if (!isset($connections['read']) || !is_array($connections['read']) || empty($connections['read'])) {
            throw ReadWriteConfigException::emptyReadConnections();
        }

        $readStrategy = $connections['read_strategy'] ?? self::STRATEGY_RANDOM;

        if (!in_array($readStrategy, [self::STRATEGY_RANDOM, self::STRATEGY_WEIGHTED], true)) {
            throw ReadWriteConfigException::unknownReadStrategy($readStrategy);
        }

        if ($readStrategy === self::STRATEGY_WEIGHTED) {
            foreach ($connections['read'] as $readConnection) {
                if (isset($readConnection['weight'])) {
                    $weight = $readConnection['weight'];

                    if (!is_int($weight) || $weight <= 0) {
                        throw ReadWriteConfigException::invalidWeight($weight);
                    }
                }
            }
        }

        return new self(
            write: $connections['write'],
            reads: $connections['read'],
            readStrategy: $readStrategy,
        );
    }
}
