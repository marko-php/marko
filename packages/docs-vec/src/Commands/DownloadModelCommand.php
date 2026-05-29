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

    /**
     * HuggingFace source, pinned to an exact commit so the bytes (and the
     * checksums below) never shift under us. Override with --base-url=<mirror>
     * for air-gapped or proxied environments; checksums are still enforced.
     */
    private const string BASE_URL =
        'https://huggingface.co/Xenova/bge-small-en-v1.5/resolve/ea104dacec62c0de699686887e3f920caeb4f3e3';

    /**
     * filename => [relative path under BASE_URL, expected sha256]
     *
     * @var array<string, array{path: string, sha256: string}>
     */
    private const array MODEL_FILES = [
        'model.onnx' => [
            'path' => 'onnx/model.onnx',
            'sha256' => '828e1496d7fabb79cfa4dcd84fa38625c0d3d21da474a00f08db0f559940cf35',
        ],
        'tokenizer.json' => [
            'path' => 'tokenizer.json',
            'sha256' => 'd241a60d5e8f04cc1b2b3e9ef7a4921b27bf526d9f6050ab90f9267a1f9e5c66',
        ],
        'config.json' => [
            'path' => 'config.json',
            'sha256' => 'fa73f90bf92c8cace1fbcb709626306f2bdbc9ea3e5b5f94b440df9b6aa56350',
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
        $baseUrl = rtrim($input->getOption('base-url') ?? self::BASE_URL, '/');
        $modelDir = $this->packageRoot . self::MODEL_DIR;

        if (!is_dir($modelDir)) {
            mkdir($modelDir, 0755, true);
        }

        foreach (self::MODEL_FILES as $filename => $meta) {
            $target = $modelDir . '/' . $filename;

            if ($this->shouldSkip($target, $meta['sha256'])) {
                $output->writeLine('Skipping ' . $filename . ' (already present, checksum matches)');
                continue;
            }

            $url = $baseUrl . '/' . $meta['path'];
            $output->writeLine('Downloading ' . $filename . ' ...');

            $data = @file_get_contents($url);
            if ($data === false || $data === '') {
                throw VecRuntimeException::downloadFailed($url);
            }

            file_put_contents($target, $data);
            $this->verifyChecksum($target, $meta['sha256']);
            $output->writeLine('Saved ' . $filename . ' (checksum verified)');
        }

        $output->writeLine('Model files downloaded successfully.');

        return 0;
    }

    /**
     * @throws VecRuntimeException
     */
    public function verifyChecksum(
        string $file,
        string $expectedSha,
    ): void
    {
        $actual = hash_file('sha256', $file);

        if ($actual !== $expectedSha) {
            throw VecRuntimeException::checksumMismatch($file, $expectedSha, $actual);
        }
    }

    private function shouldSkip(
        string $target,
        string $sha256,
    ): bool
    {
        return file_exists($target) && hash_file('sha256', $target) === $sha256;
    }
}
