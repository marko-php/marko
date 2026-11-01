<?php

declare(strict_types=1);

use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;
use Marko\Session\Flash\SessionFlashData;
use Marko\Testing\Fake\FakeSession;

it('FakeSession ages next-request flash data on every start like Session', function (): void {
    $session = new FakeSession();
    $flash = new SessionFlashData($session);

    $session->start();
    $flash->put('notice', 'Saved');
    $session->save();

    $session->start();
    $visibleOnNextRequest = $flash->get('notice');
    $session->save();

    $session->start();

    expect($visibleOnNextRequest)->toBe('Saved')
        ->and($flash->has('notice'))->toBeFalse()
        ->and($session->has(SessionFlashData::SESSION_KEY))->toBeFalse();
});

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

it('FakeSession reports itself unmodified until a value is written', function (): void {
    $session = new FakeSession();
    $session->start();
    $session->get('key');

    expect($session->isModified())->toBeFalse();

    $session->set('key', 'value');

    expect($session->isModified())->toBeTrue();
});

it('FakeSession reports itself modified after a flash message or regeneration', function (): void {
    $flashed = new FakeSession();
    $flashed->start();
    $flashed->flash()->add('success', 'Saved');

    $regenerated = new FakeSession();
    $regenerated->start();
    $regenerated->regenerate();

    expect($flashed->isModified())->toBeTrue()
        ->and($regenerated->isModified())->toBeTrue();
});

it('FakeSession records a discard without saving', function (): void {
    $session = new FakeSession();
    $session->start();

    $session->discard();

    expect($session->discarded)->toBeTrue()
        ->and($session->saved)->toBeFalse()
        ->and($session->started)->toBeFalse();
});

it('FakeSession is not available until armed or started', function (): void {
    $session = new FakeSession();

    expect($session->isAvailable())->toBeFalse();

    $session->start();

    expect($session->isAvailable())->toBeTrue();
});

it('FakeSession records that it was armed', function (): void {
    $session = new FakeSession();

    $session->arm();

    expect($session->armed)->toBeTrue()
        ->and($session->isAvailable())->toBeTrue()
        ->and($session->started)->toBeFalse();
});

it('FakeSession starts lazily on first access once armed', function (): void {
    $session = new FakeSession();
    $session->arm();

    $session->get('key');

    expect($session->started)->toBeTrue();
});

it('FakeSession stays unstarted when accessed without being armed', function (): void {
    $session = new FakeSession();

    $session->set('key', 'value');

    expect($session->started)->toBeFalse()
        ->and($session->get('key'))->toBe('value');
});

it('FakeSession is no longer armed after save', function (): void {
    $session = new FakeSession();
    $session->arm();

    $session->save();

    expect($session->armed)->toBeFalse()
        ->and($session->isAvailable())->toBeFalse();
});

it('FakeSession is no longer armed after discard', function (): void {
    $session = new FakeSession();
    $session->arm();

    $session->discard();

    expect($session->armed)->toBeFalse()
        ->and($session->isAvailable())->toBeFalse();
});
