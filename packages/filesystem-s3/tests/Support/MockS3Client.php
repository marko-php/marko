<?php

declare(strict_types=1);

namespace Marko\Filesystem\S3\Tests\Support;

use Aws\CommandInterface;
use Aws\Result;
use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use Marko\Filesystem\S3\Config\S3Config;
use RuntimeException;

class MockS3Client extends S3Client
{
    /** @var array<int, array{method: string, args: array}> */
    public array $calls = [];

    /**
     * @param array<string, callable|S3Exception> $handlers
     * @noinspection PhpMissingParentConstructorInspection
     */
    public function __construct(
        private readonly array $handlers = [],
    ) {}

    public function getCommand(
        $name,
        array $args = [],
    ) {
        $this->calls[] = ['method' => 'getCommand:' . $name, 'args' => $args];

        if (isset($this->handlers['getCommand'])) {
            $handler = $this->handlers['getCommand'];

            return is_callable($handler)
                ? $handler($name, $args)
                : $handler;
        }

        return new MockCommand($name);
    }

    public function createPresignedRequest(
        CommandInterface $command,
        $expires,
        array $options = [],
    ) {
        $this->calls[] = [
            'method' => 'createPresignedRequest',
            'args' => [
                'command' => $command,
                'expires' => $expires,
                'options' => $options,
            ],
        ];

        if (isset($this->handlers['createPresignedRequest'])) {
            $handler = $this->handlers['createPresignedRequest'];

            return is_callable($handler)
                ? $handler($command, $expires, $options)
                : $handler;
        }

        throw new RuntimeException('createPresignedRequest handler not configured');
    }

    public function __call(
        $name,
        array $args,
    ) {
        $arguments = $args[0] ?? [];
        $this->calls[] = ['method' => $name, 'args' => $arguments];

        if (!isset($this->handlers[$name])) {
            return new Result([]);
        }

        $handler = $this->handlers[$name];

        if ($handler instanceof S3Exception) {
            throw $handler;
        }

        if (is_callable($handler)) {
            $result = $handler($arguments);

            if ($result instanceof S3Exception) {
                throw $result;
            }

            return $result instanceof Result ? $result : new Result($result);
        }

        return $handler instanceof Result ? $handler : new Result($handler);
    }

    /**
     * @param array<string, callable|S3Exception> $handlers
     */
    public static function create(
        array $handlers = [],
    ): self {
        return new self($handlers);
    }

    public static function createConfig(
        string $prefix = '',
    ): S3Config {
        return new S3Config(
            bucket: 'test-bucket',
            region: 'us-east-1',
            key: 'test-key',
            secret: 'test-secret',
            prefix: $prefix,
        );
    }

    public static function createException(
        string $code = 'NoSuchKey',
        int $statusCode = 404,
    ): S3Exception {
        return new S3Exception(
            'Error',
            new MockCommand(),
            ['code' => $code, 'response' => new Response($statusCode)],
        );
    }
}
