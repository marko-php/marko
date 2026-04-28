<?php

declare(strict_types=1);

namespace Marko\DocsVec\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DocsVec\Exceptions\VecRuntimeException;

#[Command(name: 'docs-vec:download-model', description: 'Download bge-small-en-v1.5 ONNX model for docs-vec')]
class DownloadModelCommand implements CommandInterface
{
    private const string MODEL_DIR = '/resources/models/bge-small-en-v1.5';

    private const array MODEL_FILES = [
        'model.onnx' => [
            'url' => 'https://huggingface.co/Xenova/bge-small-en-v1.5/resolve/main/onnx/model.onnx',
            'sha256' => '',
        ],
        'tokenizer.json' => [
            'url' => 'https://huggingface.co/Xenova/bge-small-en-v1.5/resolve/main/tokenizer.json',
            'sha256' => '',
        ],
        'config.json' => [
            'url' => 'https://huggingface.co/Xenova/bge-small-en-v1.5/resolve/main/config.json',
            'sha256' => '',
        ],
    ];

    public function __construct(
        private string $packageRoot,
    ) {}

    /**
     * @throws VecRuntimeException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $modelDir = $this->packageRoot . self::MODEL_DIR;

        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0755, true);
        }

        foreach (self::MODEL_FILES as $filename => $meta) {
            $target = $modelDir . '/' . $filename;

            if ($this->shouldSkip($target, $meta['sha256'])) {
                $output->writeLine('Skipping ' . $filename . ' (already exists, checksum matches)');
                continue;
            }

            $output->writeLine('Downloading ' . $filename . '...');
            $data = file_get_contents($meta['url']);
            file_put_contents($target, $data);
            $output->writeLine('Saved ' . $filename);

            if ($meta['sha256'] !== '') {
                $this->verifyChecksum($target, $meta['sha256']);
            }
        }

        $output->writeLine('Model files downloaded successfully.');

        return 0;
    }

    /**
     * @throws VecRuntimeException
     */
    public function verifyChecksum(string $file, string $expectedSha): void
    {
        $actual = hash_file('sha256', $file);

        if ($actual !== $expectedSha) {
            throw VecRuntimeException::checksumMismatch($file, $expectedSha, $actual);
        }
    }

    private function shouldSkip(string $target, string $sha256): bool
    {
        if (!file_exists($target)) {
            return false;
        }

        if ($sha256 === '') {
            return true;
        }

        return hash_file('sha256', $target) === $sha256;
    }
}
