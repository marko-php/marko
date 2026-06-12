<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;
use Marko\Testing\Fake\FakeSession;

it('FakeSession implements SessionInterface', function (): void {
    $session = new FakeSession();

    expect($session)->toBeInstanceOf(SessionInterface::class);
});

it('FakeSession stores and retrieves values in memory', function (): void {
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

it('FakeSession tracks whether session was started', function (): void {
    $session = new FakeSession();

    expect($session->started)->toBeFalse();

    $session->start();

    expect($session->started)->toBeTrue();
});

it('FakeSession tracks whether session was regenerated', function (): void {
    $session = new FakeSession();

    expect($session->regenerated)->toBeFalse();

    $session->regenerate();

    expect($session->regenerated)->toBeTrue();
});

it('FakeSession supports flash messages via FlashBag', function (): void {
    $session = new FakeSession();

    expect($session->flash())->toBeInstanceOf(FlashBag::class);

    $session->flash()->add('success', 'Saved!');

    expect($session->flash()->peek('success'))->toBe(['Saved!'])
        ->and($session->flash()->get('success'))->toBe(['Saved!'])
        ->and($session->flash()->get('success'))->toBe([]);
});

it('FakeSession generates and tracks session IDs', function (): void {
    $session = new FakeSession();

    $id = $session->getId();
    expect($id)->toBeString()->not->toBeEmpty()
        ->and($session->getId())->toBe($id);

    $session->setId('custom-id');
    expect($session->getId())->toBe('custom-id');

    $session->regenerate();
    expect($session->getId())->not->toBe('custom-id');
});

it('matches production Session has semantics for null values', function (): void {
    $session = new FakeSession();

    $session->set('null-key', null);
    $session->set('value-key', 'value');

    expect($session->has('null-key'))->toBeTrue()
        ->and($session->has('value-key'))->toBeTrue()
        ->and($session->has('missing-key'))->toBeFalse();
});

it('reports a removed key as absent', function (): void {
    $session = new FakeSession();

    $session->set('key', null);
    $session->remove('key');

    expect($session->has('key'))->toBeFalse();
});

it('reports a key with a non-null value as present', function (): void {
    $session = new FakeSession();

    $session->set('key', 'value');

    expect($session->has('key'))->toBeTrue();
});

it('reports a never-set key as absent', function (): void {
    $session = new FakeSession();

    expect($session->has('missing'))->toBeFalse();
});

it('reports a key with a stored null value as present', function (): void {
    $session = new FakeSession();

    $session->set('key', null);

    expect($session->has('key'))->toBeTrue();
});

it('FakeSession clears all stored values', function (): void {
    $session = new FakeSession();

    $session->set('key1', 'value1');
    $session->set('key2', 'value2');

    expect($session->all())->toHaveCount(2);

    $session->clear();

    expect($session->all())->toBeEmpty()
        ->and($session->has('key1'))->toBeFalse();
});
