<?php

declare(strict_types=1);

use Marko\Translation\Exceptions\TranslationException;
use Marko\Translation\File\Loader\FileTranslationLoader;

function translationNsCreateTempDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/marko_ns_translation_test_' . uniqid();
    mkdir($tmpDir . '/lang/en', 0777, true);

    return $tmpDir;
}

function translationNsWriteLangFile(
    string $basePath,
    string $locale,
    string $group,
    string $content,
): void {
    $dir = $basePath . '/' . $locale;

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($dir . '/' . $group . '.php', $content);
}

function translationNsCleanupTempDir(
    string $dir,
): void {
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }

    rmdir($dir);
}

it('registers a namespace with addNamespace and loads from that path', function () {
    $appDir = translationNsCreateTempDir();
    $blogDir = sys_get_temp_dir() . '/marko_blog_lang_' . uniqid();
    mkdir($blogDir . '/en', 0777, true);
    translationNsWriteLangFile($blogDir, 'en', 'messages', '<?php return ["welcome" => "Welcome to Blog!"];');

    try {
        $loader = new FileTranslationLoader($appDir);
        $loader->addNamespace('blog', $blogDir);

        $result = $loader->load('en', 'messages', 'blog');

        expect($result)->toBe(['welcome' => 'Welcome to Blog!']);
    } finally {
        translationNsCleanupTempDir($appDir);
        translationNsCleanupTempDir($blogDir);
    }
});

it('loads namespaced translations from {namespacePath}/{locale}/{group}.php', function () {
    $appDir = translationNsCreateTempDir();
    $blogDir = sys_get_temp_dir() . '/marko_blog_lang2_' . uniqid();
    mkdir($blogDir . '/fr', 0777, true);
    translationNsWriteLangFile($blogDir, 'fr', 'validation', '<?php return ["required" => "Ce champ est requis."];');

    try {
        $loader = new FileTranslationLoader($appDir);
        $loader->addNamespace('blog', $blogDir);

        $result = $loader->load('fr', 'validation', 'blog');

        expect($result)->toBe(['required' => 'Ce champ est requis.']);
    } finally {
        translationNsCleanupTempDir($appDir);
        translationNsCleanupTempDir($blogDir);
    }
});

it('returns empty array when namespaced translation file does not exist', function () {
    $appDir = translationNsCreateTempDir();
    $blogDir = sys_get_temp_dir() . '/marko_blog_lang3_' . uniqid();
    mkdir($blogDir, 0777, true);

    try {
        $loader = new FileTranslationLoader($appDir);
        $loader->addNamespace('blog', $blogDir);

        $result = $loader->load('en', 'nonexistent', 'blog');

        expect($result)->toBe([]);
    } finally {
        translationNsCleanupTempDir($appDir);
        translationNsCleanupTempDir($blogDir);
    }
});

it('caches namespaced translations independently from default translations', function () {
    $appDir = translationNsCreateTempDir();
    file_put_contents($appDir . '/lang/en/messages.php', '<?php return ["welcome" => "App Welcome!"];');
    $blogDir = sys_get_temp_dir() . '/marko_blog_lang4_' . uniqid();
    mkdir($blogDir . '/en', 0777, true);
    translationNsWriteLangFile($blogDir, 'en', 'messages', '<?php return ["welcome" => "Blog Welcome!"];');

    try {
        $loader = new FileTranslationLoader($appDir);
        $loader->addNamespace('blog', $blogDir);

        $appMessages = $loader->load('en', 'messages');
        $blogMessages = $loader->load('en', 'messages', 'blog');

        expect($appMessages)->toBe(['welcome' => 'App Welcome!'])
            ->and($blogMessages)->toBe(['welcome' => 'Blog Welcome!']);
    } finally {
        translationNsCleanupTempDir($appDir);
        translationNsCleanupTempDir($blogDir);
    }
});

it('supports multiple namespaces simultaneously', function () {
    $appDir = translationNsCreateTempDir();
    $blogDir = sys_get_temp_dir() . '/marko_blog_lang5_' . uniqid();
    $shopDir = sys_get_temp_dir() . '/marko_shop_lang5_' . uniqid();
    mkdir($blogDir . '/en', 0777, true);
    mkdir($shopDir . '/en', 0777, true);
    translationNsWriteLangFile($blogDir, 'en', 'messages', '<?php return ["title" => "Blog Title"];');
    translationNsWriteLangFile($shopDir, 'en', 'messages', '<?php return ["title" => "Shop Title"];');

    try {
        $loader = new FileTranslationLoader($appDir);
        $loader->addNamespace('blog', $blogDir);
        $loader->addNamespace('shop', $shopDir);

        $blogMessages = $loader->load('en', 'messages', 'blog');
        $shopMessages = $loader->load('en', 'messages', 'shop');

        expect($blogMessages)->toBe(['title' => 'Blog Title'])
            ->and($shopMessages)->toBe(['title' => 'Shop Title']);
    } finally {
        translationNsCleanupTempDir($appDir);
        translationNsCleanupTempDir($blogDir);
        translationNsCleanupTempDir($shopDir);
    }
});

it('throws TranslationException when loading from unregistered namespace', function () {
    $appDir = translationNsCreateTempDir();

    try {
        $loader = new FileTranslationLoader($appDir);
        $loader->load('en', 'messages', 'unregistered');
    } finally {
        translationNsCleanupTempDir($appDir);
    }
})->throws(TranslationException::class, "Translation namespace 'unregistered' is not registered");
