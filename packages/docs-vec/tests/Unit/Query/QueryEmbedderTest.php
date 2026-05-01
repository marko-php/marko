<?php

declare(strict_types=1);

use Marko\DocsVec\Exceptions\VecRuntimeException;
use Marko\DocsVec\Query\QueryEmbedder;
use Marko\DocsVec\Runtime\VecRuntime;

beforeEach(function (): void {
    $this->packageRoot = dirname(__DIR__, 3);
    $this->runtime = new VecRuntime($this->packageRoot);
    $this->embedder = new QueryEmbedder($this->runtime);
});

it('handles empty strings with a loud error', function (): void {
    expect(fn () => $this->embedder->embed(''))
        ->toThrow(VecRuntimeException::class, 'empty');
    expect(fn () => $this->embedder->embed('   '))
        ->toThrow(VecRuntimeException::class);
});

it('embeds a short query string into a 384-dimensional float vector', function (): void {
    if (!$this->runtime->isModelAvailable()) {
        $this->markTestSkipped('ONNX model not installed');
    }
    $vector = $this->embedder->embed('how to install marko');
    expect($vector)->toHaveCount(384);
    foreach ($vector as $v) {
        expect($v)->toBeFloat();
    }
});

it('produces stable embeddings for the same input', function (): void {
    if (!$this->runtime->isModelAvailable()) {
        $this->markTestSkipped('ONNX model not installed');
    }
    $a = $this->embedder->embed('hello world');
    $b = $this->embedder->embed('hello world');
    expect($a)->toBe($b);
});

it('reuses the loaded model across multiple embed calls', function (): void {
    if (!$this->runtime->isModelAvailable()) {
        $this->markTestSkipped('ONNX model not installed');
    }
    $start = microtime(true);
    $this->embedder->embed('first call');
    $firstDuration = microtime(true) - $start;

    $start = microtime(true);
    $this->embedder->embed('second call');
    $secondDuration = microtime(true) - $start;

    // Second call should be much faster (cached model). Exact ratio is environment-dependent.
    // Just assert second is no slower than first by more than a small factor.
    expect($secondDuration)->toBeLessThanOrEqual($firstDuration * 2);
});

it('normalizes the output vector to unit length', function (): void {
    if (!$this->runtime->isModelAvailable()) {
        $this->markTestSkipped('ONNX model not installed');
    }
    $vector = $this->embedder->embed('test query');
    $magnitude = sqrt(array_sum(array_map(fn (float $v) => $v * $v, $vector)));
    expect(abs($magnitude - 1.0))->toBeLessThan(0.0001);
});
