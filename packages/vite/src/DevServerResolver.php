<?php

declare(strict_types=1);

namespace Marko\Vite;

use Marko\Vite\Contracts\DevServerResolverInterface;
use Marko\Vite\Exceptions\DevServerUnavailableException;
use Marko\Vite\ValueObjects\DevServer;
use Marko\Vite\ValueObjects\ViteConfig;

class DevServerResolver implements DevServerResolverInterface
{
    private ?bool $reachable = null;

    public function __construct(
        private readonly ViteConfig $config,
    ) {}

    public function isDevelopment(): bool
    {
        return $this->hasRunningFrontendProcess()
            || is_file($this->config->hotFilePath)
            || $this->hasReachableConfiguredDevServer();
    }

    public function resolve(): DevServer
    {
        $url = $this->resolveUrl();

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw DevServerUnavailableException::invalidUrl($url);
        }

        return new DevServer(rtrim($url, '/'));
    }

    private function resolveUrl(): string
    {
        if (is_file($this->config->hotFilePath)) {
            $contents = file_get_contents($this->config->hotFilePath);

            if ($contents === false) {
                throw DevServerUnavailableException::fromHotFile($this->config->hotFilePath);
            }

            $url = trim($contents);

            if ($url !== '') {
                return $url;
            }
        }

        return $this->config->devServerUrl;
    }

    private function hasRunningFrontendProcess(): bool
    {
        if (!is_file($this->config->devProcessFilePath)) {
            return false;
        }

        $contents = file_get_contents($this->config->devProcessFilePath);

        if ($contents === false) {
            return false;
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded) || !isset($decoded['processes']) || !is_array($decoded['processes'])) {
            return false;
        }

        foreach ($decoded['processes'] as $process) {
            if (($process['name'] ?? null) === 'frontend' && $this->isRunningProcess($process)) {
                return true;
            }
        }

        return false;
    }

    private function hasReachableConfiguredDevServer(): bool
    {
        if ($this->reachable !== null) {
            return $this->reachable;
        }

        $parts = parse_url($this->config->devServerUrl);

        if (!is_array($parts)) {
            return $this->reachable = false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = (string) ($parts['host'] ?? '');
        $port = isset($parts['port']) ? (int) $parts['port'] : match ($scheme) {
            'https', 'wss' => 443,
            'http', 'ws' => 80,
            default => 0,
        };

        if ($host === '' || $port <= 0) {
            return $this->reachable = false;
        }

        $transport = in_array($scheme, ['https', 'wss'], true) ? 'tls' : 'tcp';
        $errorCode = 0;
        $errorMessage = '';
        set_error_handler(static fn (): bool => true);

        try {
            $connection = stream_socket_client(
                sprintf('%s://%s:%d', $transport, $host, $port),
                $errorCode,
                $errorMessage,
                0.2,
            );
        } finally {
            restore_error_handler();
        }

        if (is_resource($connection)) {
            fclose($connection);

            return $this->reachable = true;
        }

        return $this->reachable = false;
    }

    /**
     * @param array<string, mixed> $process
     */
    private function isRunningProcess(array $process): bool
    {
        $pid = $process['pid'] ?? null;

        if (!is_int($pid) && !ctype_digit((string) $pid)) {
            return false;
        }

        $pid = (int) $pid;

        if ($pid <= 0) {
            return false;
        }

        if (function_exists('posix_kill')) {
            return @posix_kill($pid, 0);
        }

        if (DIRECTORY_SEPARATOR === '/' && is_dir('/proc/' . $pid)) {
            return true;
        }

        return false;
    }
}
