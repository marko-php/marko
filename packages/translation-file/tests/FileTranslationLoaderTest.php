<?php

declare(strict_types=1);

use Marko\Translation\Contracts\TranslationLoaderInterface;
use Marko\Translation\File\Loader\FileTranslationLoader;

function translationCreateTempLangDir(): string
{
    $tmpDir = sys_get_temp_dir() . '/marko_translation_test_' . uniqid();
    mkdir($tmpDir . '/lang/en', 0777, true);

    return $tmpDir;
}

function translationWriteLangFile(
    string $basePath,
    string $locale,
    string $group,
    string $content,
): void {
    $dir = $basePath . '/lang/' . $locale;

    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    file_put_contents($dir . '/' . $group . '.php', $content);
}

function translationCleanupTempDir(
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

it('implements TranslationLoaderInterface', function () {
    $tmpDir = translationCreateTempLangDir();

    try {
        $loader = new FileTranslationLoader($tmpDir);
        expect($loader)->toBeInstanceOf(TranslationLoaderInterface::class);
    } finally {
        translationCleanupTempDir($tmpDir);
    }
});

it('loads translation array from PHP file at lang/{locale}/{group}.php', function () {
    $tmpDir = translationCreateTempLangDir();
    translationWriteLangFile(
        $tmpDir,
        'en',
        'messages',
        '<?php return ["welcome" => "Welcome!", "goodbye" => "Goodbye!"];'
    );

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $result = $loader->load('en', 'messages');

        expect($result)->toBe([
            'welcome' => 'Welcome!',
            'goodbye' => 'Goodbye!',
        ]);
    } finally {
        translationCleanupTempDir($tmpDir);
    }
});

it('returns empty array when translation file does not exist', function () {
    $tmpDir = translationCreateTempLangDir();

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $result = $loader->load('fr', 'messages');

        expect($result)->toBe([]);
    } finally {
        translationCleanupTempDir($tmpDir);
    }
});

it('caches loaded translations in memory for same locale and group', function () {
    $tmpDir = translationCreateTempLangDir();
    translationWriteLangFile($tmpDir, 'en', 'messages', '<?php return ["welcome" => "Welcome!"];');

    try {
        $loader = new FileTranslationLoader($tmpDir);

        $result1 = $loader->load('en', 'messages');
        // Delete the file to prove caching works
        unlink($tmpDir . '/lang/en/messages.php');
        $result2 = $loader->load('en', 'messages');

        expect($result1)->toBe(['welcome' => 'Welcome!'])
            ->and($result2)->toBe(['welcome' => 'Welcome!']);
    } finally {
        translationCleanupTempDir($tmpDir);
    }
});

it('loads different groups independently', function () {
    $tmpDir = translationCreateTempLangDir();
    translationWriteLangFile($tmpDir, 'en', 'messages', '<?php return ["welcome" => "Welcome!"];');
    translationWriteLangFile($tmpDir, 'en', 'validation', '<?php return ["required" => "This field is required."];');

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $messages = $loader->load('en', 'messages');
        $validation = $loader->load('en', 'validation');

        expect($messages)->toBe(['welcome' => 'Welcome!'])
            ->and($validation)->toBe(['required' => 'This field is required.']);
    } finally {
        translationCleanupTempDir($tmpDir);
    }
});

it('loads different locales independently', function () {
    $tmpDir = translationCreateTempLangDir();
    translationWriteLangFile($tmpDir, 'en', 'messages', '<?php return ["welcome" => "Welcome!"];');
    translationWriteLangFile($tmpDir, 'fr', 'messages', '<?php return ["welcome" => "Bienvenue!"];');

    try {
        $loader = new FileTranslationLoader($tmpDir);
        $english = $loader->load('en', 'messages');
        $french = $loader->load('fr', 'messages');

        expect($english)->toBe(['welcome' => 'Welcome!'])
            ->and($french)->toBe(['welcome' => 'Bienvenue!']);
    } finally {
        translationCleanupTempDir($tmpDir);
    }
});
