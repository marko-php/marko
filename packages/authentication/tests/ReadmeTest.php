<?php

declare(strict_types=1);

it('README exists in package root', function () {
    $readmePath = dirname(__DIR__) . '/README.md';

    expect(file_exists($readmePath))->toBeTrue();
});

it('README includes installation instructions', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Installation')
        ->and($content)->toContain('composer require marko/authentication');
});

it('README includes configuration examples', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Configuration')
        ->and($content)->toContain('config/authentication.php');
});

it('README includes usage examples', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('## Usage')
        ->and($content)->toContain('AuthManager')
        ->and($content)->toContain('check()')
        ->and($content)->toContain('attempt(');
});

it('README documents guards', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('Guard')
        ->and($content)->toContain('SessionGuard')
        ->and($content)->toContain('TokenGuard');
});

it('README documents middleware', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('Middleware')
        ->and($content)->toContain('AuthMiddleware')
        ->and($content)->toContain('GuestMiddleware');
});

it('README documents events', function () {
    $readmePath = dirname(__DIR__) . '/README.md';
    $content = file_get_contents($readmePath);

    expect($content)->toContain('Event')
        ->and($content)->toContain('LoginEvent')
        ->and($content)->toContain('LogoutEvent')
        ->and($content)->toContain('FailedLoginEvent');
});
