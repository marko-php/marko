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
        // Stored under the same relative path locally so transformers-php's HF
        // layout resolves: <model>/onnx/model.onnx, <model>/config.json, etc.
        'onnx/model.onnx' => [
            'path' => 'onnx/model.onnx',
            'sha256' => '828e1496d7fabb79cfa4dcd84fa38625c0d3d21da474a00f08db0f559940cf35',
        ],
        'config.json' => [
            'path' => 'config.json',
            'sha256' => 'fa73f90bf92c8cace1fbcb709626306f2bdbc9ea3e5b5f94b440df9b6aa56350',
        ],
        'tokenizer.json' => [
            'path' => 'tokenizer.json',
            'sha256' => 'd241a60d5e8f04cc1b2b3e9ef7a4921b27bf526d9f6050ab90f9267a1f9e5c66',
        ],
        'tokenizer_config.json' => [
            'path' => 'tokenizer_config.json',
            'sha256' => '9261e7d79b44c8195c1cada2b453e55b00aeb81e907a6664974b4d7776172ab3',
        ],
        'special_tokens_map.json' => [
            'path' => 'special_tokens_map.json',
            'sha256' => 'b6d346be366a7d1d48332dbc9fdf3bf8960b5d879522b7799ddba59e76237ee3',
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

            $subDir = dirname($target);

            if (!is_dir($subDir)) {
                mkdir($subDir, 0755, true);
            }

            $url = $baseUrl . '/' . $meta['path'];
            $output->writeLine('Downloading ' . $filename . ' ...');

            $this->streamToFile($url, $target);
            $this->verifyChecksum($target, $meta['sha256']);
            $output->writeLine('Saved ' . $filename . ' (checksum verified)');
        }

        $output->writeLine('Model files downloaded successfully.');

        return 0;
    }

    /**
     * Stream a remote file to disk. Copies chunk-by-chunk so a multi-hundred-MB
     * model never has to fit in `memory_limit` (the old file_get_contents() OOM'd).
     *
     * @throws VecRuntimeException
     */
    private function streamToFile(
        string $url,
        string $target,
    ): void {
        $in = @fopen($url, 'rb');

        if ($in === false) {
            throw VecRuntimeException::downloadFailed($url);
        }

        $out = @fopen($target, 'wb');

        if ($out === false) {
            fclose($in);
            throw VecRuntimeException::downloadFailed($url);
        }

        $bytes = stream_copy_to_stream($in, $out);
        fclose($in);
        fclose($out);

        if ($bytes === false || $bytes === 0) {
            @unlink($target);
            throw VecRuntimeException::downloadFailed($url);
        }
    }

    /**
     * @throws VecRuntimeException
     */
    public function verifyChecksum(
        string $file,
        string $expectedSha,
    ): void {
        $actual = hash_file('sha256', $file);

        if ($actual !== $expectedSha) {
            throw VecRuntimeException::checksumMismatch($file, $expectedSha, $actual);
        }
    }

    private function shouldSkip(
        string $target,
        string $sha256,
    ): bool {
        return file_exists($target) && hash_file('sha256', $target) === $sha256;
    }
}
