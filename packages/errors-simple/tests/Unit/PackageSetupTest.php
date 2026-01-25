<?php

declare(strict_types=1);

use Marko\Errors\Contracts\ErrorHandlerInterface;
use Marko\ErrorsSimple\CodeSnippetExtractor;
use Marko\ErrorsSimple\Environment;
use Marko\ErrorsSimple\Formatters\BasicHtmlFormatter;
use Marko\ErrorsSimple\Formatters\TextFormatter;
use Marko\ErrorsSimple\SimpleErrorHandler;

describe('Package Setup', function () {
    $composerJsonPath = dirname(__DIR__, 2) . '/composer.json';
    $composerJson = json_decode(file_get_contents($composerJsonPath), true);
    $modulePath = dirname(__DIR__, 2) . '/module.php';

    it('has valid composer.json with name marko/errors-simple', function () use ($composerJson) {
        expect($composerJson['name'])->toBe('marko/errors-simple');
    });

    it('requires php 8.5 or higher', function () use ($composerJson) {
        expect($composerJson['require']['php'])->toMatch('/\^?>=?8\.5/');
    });

    it('requires marko/core', function () use ($composerJson) {
        expect($composerJson['require'])->toHaveKey('marko/core');
    });

    it('requires marko/errors', function () use ($composerJson) {
        expect($composerJson['require'])->toHaveKey('marko/errors');
    });

    it('has no other dependencies', function () use ($composerJson) {
        $expectedDeps = ['php', 'marko/core', 'marko/errors'];
        $actualDeps = array_keys($composerJson['require']);
        sort($expectedDeps);
        sort($actualDeps);
        expect($actualDeps)->toBe($expectedDeps);
    });

    it('has PSR-4 autoloading for Marko\\ErrorsSimple namespace', function () use ($composerJson) {
        expect($composerJson['autoload']['psr-4'])->toHaveKey('Marko\\ErrorsSimple\\');
        expect($composerJson['autoload']['psr-4']['Marko\\ErrorsSimple\\'])->toBe('src/');
    });

    it('binds SimpleErrorHandler to ErrorHandlerInterface in module.php', function () use ($modulePath) {
        $module = require $modulePath;
        expect($module['bindings'])->toHaveKey(ErrorHandlerInterface::class);
        expect($module['bindings'][ErrorHandlerInterface::class])->toBe(SimpleErrorHandler::class);
    });

    it('auto-registers error handler via module boot hook', function () use ($modulePath) {
        $module = require $modulePath;
        expect($module)->toHaveKey('boot');
        expect($module['boot'])->toBeCallable();
    });

    it('exports SimpleErrorHandler', function () {
        expect(class_exists(SimpleErrorHandler::class))->toBeTrue();
    });

    it('exports TextFormatter', function () {
        expect(class_exists(TextFormatter::class))->toBeTrue();
    });

    it('exports BasicHtmlFormatter', function () {
        expect(class_exists(BasicHtmlFormatter::class))->toBeTrue();
    });

    it('exports CodeSnippetExtractor', function () {
        expect(class_exists(CodeSnippetExtractor::class))->toBeTrue();
    });

    it('exports Environment', function () {
        expect(class_exists(Environment::class))->toBeTrue();
    });
});
