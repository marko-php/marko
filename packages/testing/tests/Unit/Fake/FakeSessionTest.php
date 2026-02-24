<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;
use Marko\Testing\Fake\FakeSession;

it('FakeSession implements SessionInterface', function () {
    $session = new FakeSession();

    expect($session)->toBeInstanceOf(SessionInterface::class);
});

it('FakeSession stores and retrieves values in memory', function () {
    $session = new FakeSession();

    $session->set('key', 'value');
    $session->set('other', 42);

    expect($session->get('key'))->toBe('value')
        ->and($session->get('other'))->toBe(42)
        ->and($session->get('missing'))->toBeNull()
        ->and($session->get('missing', 'default'))->toBe('default')
        ->and($session->has('key'))->toBeTrue()
        ->and($session->has('missing'))->toBeFalse()
        ->and($session->all())->toBe(['key' => 'value', 'other' => 42]);

    $session->remove('key');
    expect($session->has('key'))->toBeFalse();
});

it('FakeSession tracks whether session was started', function () {
    $session = new FakeSession();

    expect($session->started)->toBeFalse();

    $session->start();

    expect($session->started)->toBeTrue();
});

it('FakeSession tracks whether session was regenerated', function () {
    $session = new FakeSession();

    expect($session->regenerated)->toBeFalse();

    $session->regenerate();

    expect($session->regenerated)->toBeTrue();
});

it('FakeSession supports flash messages via FlashBag', function () {
    $session = new FakeSession();

    expect($session->flash())->toBeInstanceOf(FlashBag::class);

    $session->flash()->add('success', 'Saved!');

    expect($session->flash()->peek('success'))->toBe(['Saved!'])
        ->and($session->flash()->get('success'))->toBe(['Saved!'])
        ->and($session->flash()->get('success'))->toBe([]);
});

it('FakeSession generates and tracks session IDs', function () {
    $session = new FakeSession();

    $id = $session->getId();
    expect($id)->toBeString()->not->toBeEmpty()
        ->and($session->getId())->toBe($id);

    $session->setId('custom-id');
    expect($session->getId())->toBe('custom-id');

    $session->regenerate();
    expect($session->getId())->not->toBe('custom-id');
});

it('FakeSession clears all stored values', function () {
    $session = new FakeSession();

    $session->set('key1', 'value1');
    $session->set('key2', 'value2');

    expect($session->all())->toHaveCount(2);

    $session->clear();

    expect($session->all())->toBeEmpty()
        ->and($session->has('key1'))->toBeFalse();
});
