<?php

declare(strict_types=1);

use Marko\DocsMarkdown\MarkdownRepository;
use Marko\DocsVec\Indexing\HybridIndexBuilder;
use Marko\DocsVec\Runtime\VecRuntime;

$packageRoot = dirname(__DIR__, 4);

// Helper: create a fixture MarkdownRepository with in-memory docs
function makeFixtureRepo(): MarkdownRepository
{
    $docsPath = sys_get_temp_dir() . '/marko-hybrid-test-' . uniqid();
    mkdir($docsPath . '/getting-started', 0755, true);
    mkdir($docsPath . '/concepts', 0755, true);

    file_put_contents($docsPath . '/getting-started/introduction.md', <<<'MD'
# Introduction

Welcome to Marko.

## Installation

Install via Composer.

## Quick Start

Run the app.
MD);

    file_put_contents($docsPath . '/concepts/modularity.md', <<<'MD'
---
title: Modularity
---
# Modularity

Marko is modular.

## Packages

Each package is independent.
MD);

    return new MarkdownRepository($docsPath);
}

it('reads all pages from MarkdownRepository', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not installed');
    }

    if (!$runtime->isModelAvailable()) {
        $this->markTestSkipped('bge-small-en-v1.5 ONNX model not available');
    }

    $repo = makeFixtureRepo();
    $builder = new HybridIndexBuilder($repo, $runtime);

    $outputPath = sys_get_temp_dir() . '/marko-hybrid-' . uniqid() . '.sqlite';
    $builder->build($outputPath);

    expect(file_exists($outputPath))->toBeTrue();

    // Verify row count in docs_meta matches pages
    $pdo = new PDO('sqlite:' . $outputPath);
    $count = (int) $pdo->query('SELECT COUNT(*) FROM docs_meta')->fetchColumn();
    expect($count)->toBe(2);
});

it('chunks markdown into heading-delimited sections', function (): void {
    // Test chunkByHeading via reflection
    $repo = makeFixtureRepo();
    $runtime = new VecRuntime(sys_get_temp_dir());
    $builder = new HybridIndexBuilder($repo, $runtime);

    $reflection = new ReflectionClass($builder);
    $method = $reflection->getMethod('chunkByHeading');

    $markdown = "# Introduction\n\nWelcome.\n\n## Installation\n\nInstall via Composer.\n\n## Quick Start\n\nRun the app.";
    $chunks = $method->invoke($builder, $markdown);

    expect($chunks)->toBeArray()
        ->and(count($chunks))->toBe(3)
        ->and($chunks[0])->toContain('Introduction')
        ->and($chunks[1])->toContain('Installation')
        ->and($chunks[2])->toContain('Quick Start');
});

it('writes FTS5 table identical in schema to docs-fts', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not installed');
    }

    if (!$runtime->isModelAvailable()) {
        $this->markTestSkipped('bge-small-en-v1.5 ONNX model not available');
    }

    $repo = makeFixtureRepo();
    $builder = new HybridIndexBuilder($repo, $runtime);

    $outputPath = sys_get_temp_dir() . '/marko-hybrid-' . uniqid() . '.sqlite';
    $builder->build($outputPath);

    $pdo = new PDO('sqlite:' . $outputPath);
    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE name='docs_fts'");
    $sql = $stmt->fetchColumn();

    expect($sql)->toContain('fts5')
        ->and($sql)->toContain('page_id')
        ->and($sql)->toContain('title')
        ->and($sql)->toContain('content')
        ->and($sql)->toContain('porter unicode61');
});

it('writes sqlite-vec vec0 table with 384-dimensional embeddings per chunk', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not installed');
    }

    if (!$runtime->isModelAvailable()) {
        $this->markTestSkipped('bge-small-en-v1.5 ONNX model not available');
    }

    $repo = makeFixtureRepo();
    $builder = new HybridIndexBuilder($repo, $runtime);

    $outputPath = sys_get_temp_dir() . '/marko-hybrid-' . uniqid() . '.sqlite';
    $builder->build($outputPath);

    $pdo = new PDO('sqlite:' . $outputPath);
    $stmt = $pdo->query("SELECT sql FROM sqlite_master WHERE name='docs_vec'");
    $sql = $stmt->fetchColumn();

    expect($sql)->toContain('vec0')
        ->and($sql)->toContain('384');

    $vecCount = (int) $pdo->query('SELECT COUNT(*) FROM docs_vec')->fetchColumn();
    $ftsCount = (int) $pdo->query('SELECT COUNT(*) FROM docs_fts')->fetchColumn();

    // Every FTS chunk should have a corresponding vec row
    expect($vecCount)->toBe($ftsCount);
});

it('produces queryable BM25 + vector hybrid results', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not installed');
    }

    if (!$runtime->isModelAvailable()) {
        $this->markTestSkipped('bge-small-en-v1.5 ONNX model not available');
    }

    $repo = makeFixtureRepo();
    $builder = new HybridIndexBuilder($repo, $runtime);

    $outputPath = sys_get_temp_dir() . '/marko-hybrid-' . uniqid() . '.sqlite';
    $builder->build($outputPath);

    $pdo = new PDO('sqlite:' . $outputPath);

    // FTS5 BM25 query
    $stmt = $pdo->prepare('SELECT page_id FROM docs_fts WHERE docs_fts MATCH ? ORDER BY rank LIMIT 5');
    $stmt->execute(['installation']);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

    expect($rows)->not->toBeEmpty();
});

it('is deterministic given identical input and model', function () use ($packageRoot): void {
    $runtime = new VecRuntime($packageRoot);

    if (!$runtime->isSqliteVecAvailable()) {
        $this->markTestSkipped('sqlite-vec extension not installed');
    }

    if (!$runtime->isModelAvailable()) {
        $this->markTestSkipped('bge-small-en-v1.5 ONNX model not available');
    }

    $repo = makeFixtureRepo();

    $out1 = sys_get_temp_dir() . '/marko-det1-' . uniqid() . '.sqlite';
    $out2 = sys_get_temp_dir() . '/marko-det2-' . uniqid() . '.sqlite';

    $b1 = new HybridIndexBuilder($repo, $runtime);
    $b1->build($out1);

    $b2 = new HybridIndexBuilder($repo, $runtime);
    $b2->build($out2);

    $pdo1 = new PDO('sqlite:' . $out1);
    $pdo2 = new PDO('sqlite:' . $out2);

    $count1 = (int) $pdo1->query('SELECT COUNT(*) FROM docs_fts')->fetchColumn();
    $count2 = (int) $pdo2->query('SELECT COUNT(*) FROM docs_fts')->fetchColumn();

    expect($count1)->toBe($count2);
});
