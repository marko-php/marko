<?php

declare(strict_types=1);

use Marko\Session\Flash\FlashBag;

it('adds flash message', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('success', 'Hello');

    expect($flash->peek('success'))->toBe(['Hello']);
});

it('adds multiple messages to same type', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('error', 'Error 1');
    $flash->add('error', 'Error 2');

    expect($flash->peek('error'))->toBe(['Error 1', 'Error 2']);
});

it('sets messages replacing existing', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('info', 'Original');
    $flash->set('info', ['New 1', 'New 2']);

    expect($flash->peek('info'))->toBe(['New 1', 'New 2']);
});

it('gets and clears messages', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('success', 'Hello');

    $messages = $flash->get('success');

    expect($messages)->toBe(['Hello'])
        ->and($flash->peek('success'))->toBe([]);
});

it('gets default when type missing', function () {
    $data = [];
    $flash = new FlashBag($data);

    expect($flash->get('missing', ['default']))->toBe(['default']);
});

it('peeks without clearing', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('info', 'Message');

    $peek1 = $flash->peek('info');
    $peek2 = $flash->peek('info');

    expect($peek1)->toBe(['Message'])
        ->and($peek2)->toBe(['Message']);
});

it('peeks with default when missing', function () {
    $data = [];
    $flash = new FlashBag($data);

    expect($flash->peek('missing', ['fallback']))->toBe(['fallback']);
});

it('gets all and clears', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('success', 'OK');
    $flash->add('error', 'Fail');

    $all = $flash->all();

    expect($all)->toBe([
        'success' => ['OK'],
        'error' => ['Fail'],
    ])
        ->and($flash->all())->toBe([]);
});

it('checks if type has messages', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('info', 'Message');

    expect($flash->has('info'))->toBeTrue()
        ->and($flash->has('missing'))->toBeFalse();
});

it('clears and returns messages', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('success', 'Hello');

    $cleared = $flash->clear();

    expect($cleared)->toBe(['success' => ['Hello']])
        ->and($flash->has('success'))->toBeFalse();
});

it('syncs to session data', function () {
    $data = [];
    $flash = new FlashBag($data);

    $flash->add('success', 'Message');

    expect($data)->toHaveKey('_flash')
        ->and($data['_flash'])->toBe(['success' => ['Message']]);
});

it('loads existing flash data', function () {
    $data = ['_flash' => ['info' => ['Existing']]];
    $flash = new FlashBag($data);

    expect($flash->peek('info'))->toBe(['Existing']);
});

it('removes flash key when empty', function () {
    $data = ['_flash' => ['success' => ['Message']]];
    $flash = new FlashBag($data);

    $flash->clear();

    expect(isset($data['_flash']))->toBeFalse();
});
