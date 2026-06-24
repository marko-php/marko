<?php

declare(strict_types=1);

namespace Marko\DocsVec\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\DocsVec\Exceptions\VecRuntimeException;
use PharData;
use Throwable;

#[Command(
    name: 'docs-vec:download-extension',
    description: 'Download the sqlite-vec native extension for this platform'
)]
class DownloadExtensionCommand implements CommandInterface
{
    private const string EXTENSION_DIR = '/resources/sqlite-vec';

    /**
     * Pinned to an exact sqlite-vec release so the tarball checksums below never
     * shift under us. Override the host with --base-url=<mirror> for air-gapped
     * or proxied environments; checksums are still enforced.
     */
    private const string VERSION = '0.1.9';

    private const string BASE_URL = 'https://github.com/asg017/sqlite-vec/releases/download';

    /**
     * "<PHP_OS_FAMILY>|<machine>" => [release-asset suffix, tarball sha256, extracted binary].
     * Machine aliases are normalized in {@see self::resolvePlatform()}.
     *
     * @var array<string, array{suffix: string, sha256: string, binary: string}>
     */
    private const array PLATFORMS = [
        'Darwin|arm64' => [
            'suffix' => 'macos-aarch64',
            'sha256' => '8282126333399ddfe98bbbcc7a1936e7252625aac49df056a98be602e46bfd29',
            'binary' => 'vec0.dylib',
        ],
        'Darwin|x86_64' => [
            'suffix' => 'macos-x86_64',
            'sha256' => '53ad76e400786515e2edcaed2f01271dda846316390b761fadbd2dcf56aa4713',
            'binary' => 'vec0.dylib',
        ],
        'Linux|x86_64' => [
            'suffix' => 'linux-x86_64',
            'sha256' => 'b959baa1d8dc88861b1edb337b8587178cdcb12d60b4998f9d10b6a82052d5d7',
            'binary' => 'vec0.so',
        ],
        'Linux|aarch64' => [
            'suffix' => 'linux-aarch64',
            'sha256' => 'ea03d39541e478fab5974253c461e1cb5d77742f69e40cf96e3fad5bc309a37c',
            'binary' => 'vec0.so',
        ],
        'Windows|x86_64' => [
            'suffix' => 'windows-x86_64',
            'sha256' => '51581189d52066b4dfc6631f6d7a3eab7dedc2260656ab09ca97ab3fb8165983',
            'binary' => 'vec0.dll',
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
        $platform = $this->resolvePlatform();
        $extensionDir = $this->packageRoot . self::EXTENSION_DIR;
        $target = $extensionDir . '/' . $platform['binary'];

        if (is_file($target)) {
            $output->writeLine('sqlite-vec already present at ' . $target);

            return 0;
        }

        if (!is_dir($extensionDir)) {
            mkdir($extensionDir, 0755, true);
        }

        $baseUrl = rtrim($input->getOption('base-url') ?? self::BASE_URL, '/');
        $asset = sprintf('sqlite-vec-%s-loadable-%s.tar.gz', self::VERSION, $platform['suffix']);
        $url = sprintf('%s/v%s/%s', $baseUrl, self::VERSION, $asset);

        $tarball = $extensionDir . '/' . $asset;
        $output->writeLine('Downloading ' . $asset . ' ...');
        $this->streamToFile($url, $tarball);
        $this->verifyChecksum($tarball, $platform['sha256']);

        $this->extractBinary($tarball, $platform['binary'], $extensionDir);
        @unlink($tarball);

        $output->writeLine('Saved ' . $platform['binary'] . ' (checksum verified) to ' . $extensionDir);

        return 0;
    }

    /**
     * @return array{suffix: string, sha256: string, binary: string}
     *
     * @throws VecRuntimeException
     */
    private function resolvePlatform(): array
    {
        $machine = strtolower(php_uname('m'));

        $normalized = match (true) {
            in_array($machine, ['arm64', 'aarch64'], true) => PHP_OS_FAMILY === 'Darwin' ? 'arm64' : 'aarch64',
            in_array($machine, ['x86_64', 'amd64'], true) => 'x86_64',
            default => $machine,
        };

        $key = PHP_OS_FAMILY . '|' . $normalized;

        return self::PLATFORMS[$key] ?? throw VecRuntimeException::unsupportedPlatform($key);
    }

    /**
     * @throws VecRuntimeException
     */
    private function extractBinary(
        string $tarball,
        string $binary,
        string $destDir,
    ): void {
        try {
            $archive = new PharData($tarball);
            $archive->extractTo($destDir, $binary, true);
        } catch (Throwable $e) {
            throw VecRuntimeException::extractionFailed($tarball, $e->getMessage());
        }

        if (!is_file($destDir . '/' . $binary)) {
            throw VecRuntimeException::extractionFailed(
                $tarball,
                "expected '$binary' was not found in the archive",
            );
        }
    }

    /**
     * Stream a remote file to disk without buffering it entirely in memory.
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
    private function verifyChecksum(
        string $file,
        string $expectedSha,
    ): void {
        $actual = hash_file('sha256', $file);

        if ($actual !== $expectedSha) {
            throw VecRuntimeException::checksumMismatch($file, $expectedSha, $actual);
        }
    }
}
