<?php

declare(strict_types=1);

namespace Marko\Session\File\Handler;

use Marko\Session\Contracts\SessionHandlerInterface;

class FileSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private readonly string $path,
    ) {}

    public function open(
        string $path,
        string $name,
    ): bool {
        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }

        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(
        string $id,
    ): string|false {
        $path = $this->getPath($id);

        if (!file_exists($path)) {
            return '';
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            return false;
        }

        flock($handle, LOCK_SH);
        $size = filesize($path);
        $data = $size > 0 ? fread($handle, $size) : '';
        flock($handle, LOCK_UN);
        fclose($handle);

        return $data !== false ? $data : '';
    }

    public function write(
        string $id,
        string $data,
    ): bool {
        $path = $this->getPath($id);

        $handle = fopen($path, 'c');

        if ($handle === false) {
            return false;
        }

        flock($handle, LOCK_EX);
        ftruncate($handle, 0);
        fwrite($handle, $data);
        flock($handle, LOCK_UN);
        fclose($handle);

        return true;
    }

    public function destroy(
        string $id,
    ): bool {
        $path = $this->getPath($id);

        if (file_exists($path)) {
            return unlink($path);
        }

        return true;
    }

    public function gc(
        int $maxLifetime,
    ): int|false {
        $count = 0;
        $expireTime = time() - $maxLifetime;

        $files = glob($this->path . '/sess_*');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            $mtime = filemtime($file);

            if ($mtime !== false && $mtime < $expireTime) {
                if (unlink($file)) {
                    $count++;
                }
            }
        }

        return $count;
    }

    private function getPath(
        string $id,
    ): string {
        return $this->path . '/sess_' . $id;
    }
}
