<?php

declare(strict_types=1);

namespace Marko\Broadcasting\Amphp;

use Marko\Broadcasting\Amphp\Exceptions\AmphpBroadcastException;

/**
 * Amphp broadcasting settings, built from config/broadcasting-amphp.php by the module.php binding.
 */
readonly class AmphpBroadcastingConfig
{
    /**
     * @param list<string> $allowedOrigins
     * @param list<non-empty-string> $trustedProxies
     * @throws AmphpBroadcastException When health detail is enabled without a health secret
     */
    public function __construct(
        public string $host = '0.0.0.0',
        public int $port = 8085,
        public string $path = '/stream',
        public string $healthPath = '/health',
        public bool $healthDetail = false,
        public string $healthSecret = '',
        public string $publicUrl = 'http://localhost:8085',
        public string $channelPrefix = 'broadcast.',
        public string $appKey = '',
        public int $heartbeat = 15,
        public int $replayBuffer = 100,
        public int $replayTtl = 300,
        public int $maxConnections = 10000,
        public int $maxConnectionsPerIp = 100,
        public array $allowedOrigins = ['*'],
        public array $trustedProxies = [],
        public int $tokenTtl = 3600,
        public int $logInterval = 60,
    ) {
        if (
            $this->healthDetail
            && $this->healthSecret === ''
        ) {
            throw AmphpBroadcastException::missingHealthSecret();
        }
    }
}
