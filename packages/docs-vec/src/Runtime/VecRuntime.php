<?php

declare(strict_types=1);

namespace Marko\DocsVec\Runtime;

use Codewithkyrian\Transformers\Pipelines\Pipeline;
use Marko\DocsVec\Exceptions\VecRuntimeException;
use PDO;

class VecRuntime
{
    private const string MODEL_DIR = '/resources/models/bge-small-en-v1.5';

    private const int EMBEDDING_DIM = 384;

    public function __construct(
        private string $packageRoot,
    ) {}

    /**
     * @throws VecRuntimeException
     */
    public function openConnection(string $databasePath = ':memory:'): PDO
    {
        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $extensionPath = $this->findSqliteVecExtension();

        if ($extensionPath === null) {
            throw VecRuntimeException::sqliteVecNotLoaded(
                'Extension binary not found in known locations: ' .
                '/usr/local/lib/sqlite-vec.so, /usr/local/lib/sqlite-vec.dylib, ' .
                $this->packageRoot . '/resources/sqlite-vec/vec0.so, ' .
                $this->packageRoot . '/resources/sqlite-vec/vec0.dylib',
            );
        }

        $pdo->sqliteCreateFunction('load_extension', fn () => null, 0);
        $pdo->exec("SELECT load_extension('" . $extensionPath . "')");

        return $pdo;
    }

    /**
     * @return array<int, float>
     *
     * @throws VecRuntimeException
     */
    public function embed(string $text): array
    {
        $modelPath = $this->packageRoot . self::MODEL_DIR . '/model.onnx';

        if (!file_exists($modelPath)) {
            throw VecRuntimeException::modelMissing($modelPath);
        }

        if (!class_exists('Codewithkyrian\Transformers\Pipelines\Pipeline')) {
            throw VecRuntimeException::transformersNotInstalled();
        }

        /** @var object $pipeline */
        $pipeline = Pipeline::create(
            task: 'feature-extraction',
            model: $this->packageRoot . self::MODEL_DIR,
        );

        /** @var array<int, float> $output */
        $output = $pipeline($text);

        return $output;
    }

    public function getEmbeddingDim(): int
    {
        return self::EMBEDDING_DIM;
    }

    public function isModelAvailable(): bool
    {
        return file_exists($this->packageRoot . self::MODEL_DIR . '/model.onnx');
    }

    public function isSqliteVecAvailable(): bool
    {
        return $this->findSqliteVecExtension() !== null;
    }

    private function findSqliteVecExtension(): ?string
    {
        $candidates = [
            '/usr/local/lib/sqlite-vec.so',
            '/usr/local/lib/sqlite-vec.dylib',
            $this->packageRoot . '/resources/sqlite-vec/vec0.so',
            $this->packageRoot . '/resources/sqlite-vec/vec0.dylib',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
