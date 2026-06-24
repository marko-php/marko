<?php

declare(strict_types=1);

namespace Marko\DocsVec\Runtime;

use Codewithkyrian\Transformers\Pipelines\Pipeline;

use function Codewithkyrian\Transformers\Pipelines\pipeline;

use Codewithkyrian\Transformers\Transformers;
use Marko\DocsVec\Exceptions\VecRuntimeException;
use PDO;
use Pdo\Sqlite;

use Throwable;

class VecRuntime
{
    private const string MODEL_DIR = '/resources/models/bge-small-en-v1.5';

    private const int EMBEDDING_DIM = 384;

    /**
     * Memoized feature-extraction pipeline. Building it loads the ONNX model
     * (~tens of MB), so it must be created once and reused — rebuilding per call
     * reloads the model on every chunk, exhausting memory while indexing.
     */
    private ?Pipeline $pipeline = null;

    public function __construct(
        private string $packageRoot,
    ) {}

    /**
     * Open a connection with the sqlite-vec extension loaded (vector search).
     *
     * Uses the driver-specific `Pdo\Sqlite::loadExtension()` API — NOT a
     * `SELECT load_extension(...)` SQL call, which SQLite blocks by default
     * ("not authorized"). `Pdo\Sqlite` extends `PDO`, so the return type holds.
     *
     * @throws VecRuntimeException
     */
    public function openConnection(string $databasePath = ':memory:'): PDO
    {
        $extensionPath = $this->findSqliteVecExtension();

        if ($extensionPath === null) {
            throw VecRuntimeException::sqliteVecNotLoaded(
                'Extension binary not found in known locations: ' .
                '/usr/local/lib/sqlite-vec.so, /usr/local/lib/sqlite-vec.dylib, ' .
                $this->packageRoot . '/resources/sqlite-vec/vec0.so, ' .
                $this->packageRoot . '/resources/sqlite-vec/vec0.dylib',
            );
        }

        $pdo = new Sqlite('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        try {
            $pdo->loadExtension($extensionPath);
        } catch (Throwable $e) {
            throw VecRuntimeException::sqliteVecNotLoaded(
                "Failed to load '$extensionPath': {$e->getMessage()}",
            );
        }

        return $pdo;
    }

    /**
     * Opens a plain SQLite connection without loading the sqlite-vec extension.
     * Use this when only FTS5 queries are needed (no vector search).
     */
    public function openPlainConnection(string $databasePath = ':memory:'): PDO
    {
        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }

    /**
     * @return array<int, float>
     *
     * @throws VecRuntimeException
     */
    public function embed(string $text): array
    {
        $modelDir = $this->packageRoot . self::MODEL_DIR;

        if (!file_exists($modelDir . '/onnx/model.onnx')) {
            throw VecRuntimeException::modelMissing($modelDir . '/onnx/model.onnx');
        }

        if (!self::transformersInstalled()) {
            throw VecRuntimeException::transformersNotInstalled();
        }

        if ($this->pipeline === null) {
            // transformers-php resolves files as <cacheDir>/<model>/{config.json, onnx/model.onnx, ...}.
            Transformers::setup()->setCacheDir(dirname($modelDir))->apply();

            // quantized:false → use the full-precision onnx/model.onnx we ship.
            $this->pipeline = pipeline('feature-extraction', basename($modelDir), quantized: false);
        }

        // pooling:'mean' collapses per-token states into one sentence vector;
        // normalize:true yields a unit vector ready for cosine distance. The
        // pipeline returns a batched array of shape [1, dim].
        /** @var array<int, array<int, float>> $rows */
        $rows = ($this->pipeline)($text, pooling: 'mean', normalize: true);

        return $rows[0];
    }

    public function getEmbeddingDim(): int
    {
        return self::EMBEDDING_DIM;
    }

    public function isModelAvailable(): bool
    {
        return file_exists($this->packageRoot . self::MODEL_DIR . '/onnx/model.onnx');
    }

    /**
     * True only when a usable sqlite-vec binary is present AND this PHP build can
     * actually load SQLite extensions. Both are required before opening a vector
     * connection; callers fall back to FTS5-only search otherwise.
     */
    public function isSqliteVecAvailable(): bool
    {
        return $this->findSqliteVecExtension() !== null
            && self::supportsExtensionLoading();
    }

    /**
     * Everything needed for semantic (vector) search: the extension, this PHP
     * build's ability to load it, the ONNX model, and transformers-php.
     */
    public function isVectorSearchAvailable(): bool
    {
        return $this->isSqliteVecAvailable()
            && $this->isModelAvailable()
            && self::transformersInstalled();
    }

    public static function transformersInstalled(): bool
    {
        return class_exists(Pipeline::class);
    }

    /**
     * Whether this PHP build permits loading SQLite extensions via PDO. Some
     * builds compile the capability out (`SQLITE_OMIT_LOAD_EXTENSION`) or disable
     * it; probing once avoids crashing on those — we degrade to FTS5 instead.
     */
    public static function supportsExtensionLoading(): bool
    {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        if (!class_exists(Sqlite::class) || !method_exists(Sqlite::class, 'loadExtension')) {
            return $supported = false;
        }

        try {
            // Probe with an invalid path: "unable to load" means loading is
            // permitted (the path was just bad); "disabled"/"not authorized"
            // means the capability is compiled out or blocked on this build.
            (new Sqlite('sqlite::memory:'))->loadExtension('');
        } catch (Throwable $e) {
            $message = strtolower($e->getMessage());

            if (str_contains($message, 'disabled') || str_contains($message, 'not authorized')) {
                return $supported = false;
            }
        }

        return $supported = true;
    }

    private function findSqliteVecExtension(): ?string
    {
        $candidates = [
            '/usr/local/lib/sqlite-vec.so',
            '/usr/local/lib/sqlite-vec.dylib',
            $this->packageRoot . '/resources/sqlite-vec/vec0.so',
            $this->packageRoot . '/resources/sqlite-vec/vec0.dylib',
        ];

        return array_find($candidates, fn (string $candidate): bool => file_exists($candidate));
    }
}
