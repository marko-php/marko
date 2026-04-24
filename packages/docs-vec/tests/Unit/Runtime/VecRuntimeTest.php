<?php

declare(strict_types=1);

use Marko\DocsVec\Exceptions\VecRuntimeException;
use Marko\DocsVec\Runtime\VecRuntime;

$packageRoot = dirname(__DIR__, 3);

it('opens an in-memory SQLite connection with sqlite-vec loaded', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not installed');
    }

    $pdo = $runtime->openConnection(':memory:');

    expect($pdo)->toBeInstanceOf(PDO::class);
});

it('registers the vec0 virtual table type successfully', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not installed');
    }

    $pdo = $runtime->openConnection(':memory:');
    $pdo->exec('CREATE VIRTUAL TABLE vec_test USING vec0(embedding float[4])');

    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='vec_test'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    expect($row['name'])->toBe('vec_test');
});

it('loads the bundled bge-small-en-v1.5 ONNX model from resources', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isModelAvailable()) {
        $this->markTestSkipped('bge-small-en-v1.5 ONNX model not available; run marko docs-vec:download-model');
    }

    expect($runtime->isModelAvailable())->toBeTrue();
});

it('embeds a query string into a 384-dimensional vector', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isModelAvailable()) {
        $this->markTestSkipped('bge-small-en-v1.5 ONNX model not available; run marko docs-vec:download-model');
    }

    $vector = $runtime->embed('test query');

    expect($vector)->toBeArray()
        ->and(count($vector))->toBe(384);
});

it('produces stable embeddings for identical input', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isModelAvailable()) {
        $this->markTestSkipped('bge-small-en-v1.5 ONNX model not available; run marko docs-vec:download-model');
    }

    $vector1 = $runtime->embed('stable input text');
    $vector2 = $runtime->embed('stable input text');

    expect($vector1)->toBe($vector2);
});

it('throws VecRuntimeException with helpful context when sqlite-vec cannot be loaded', function (): void {
    $runtime = new VecRuntime('/nonexistent/path');

    $runtime->openConnection(':memory:');
})->throws(VecRuntimeException::class, 'sqlite-vec extension could not be loaded');

it('throws VecRuntimeException with helpful context when ONNX model is missing, suggesting marko docs-vec:download-model', function () use ($packageRoot): void {
    // Use a temp dir that has no model files to guarantee modelMissing is thrown
    $tempRoot = sys_get_temp_dir() . '/marko-vec-test-' . uniqid();
    mkdir($tempRoot . '/resources/models/bge-small-en-v1.5', 0755, true);

    $runtime = new VecRuntime($tempRoot);

    expect($runtime->isModelAvailable())->toBeFalse();

    $runtime->embed('test');
})->throws(VecRuntimeException::class, 'ONNX model not found');
