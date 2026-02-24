<?php

declare(strict_types=1);

use Marko\Core\Event\Event;
use Marko\Core\Event\EventDispatcherInterface;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Fake\FakeEventDispatcher;

it('implements EventDispatcherInterface', function () {
    $dispatcher = new FakeEventDispatcher();

    expect($dispatcher)->toBeInstanceOf(EventDispatcherInterface::class);
});

it('captures dispatched events in memory', function () {
    $dispatcher = new FakeEventDispatcher();
    $event = new class () extends Event {};

    $dispatcher->dispatch($event);

    expect($dispatcher->dispatched)->toHaveCount(1)
        ->and($dispatcher->dispatched[0])->toBe($event);
});

it('returns all dispatched events', function () {
    $dispatcher = new FakeEventDispatcher();
    $event1 = new class () extends Event {};
    $event2 = new class () extends Event {};

    $dispatcher->dispatch($event1);
    $dispatcher->dispatch($event2);

    expect($dispatcher->dispatched)->toHaveCount(2)
        ->and($dispatcher->dispatched[0])->toBe($event1)
        ->and($dispatcher->dispatched[1])->toBe($event2);
});

it('asserts event was dispatched by class name', function () {
    $dispatcher = new FakeEventDispatcher();
    $event = new class () extends Event {};
    $class = $event::class;

    $dispatcher->dispatch($event);

    expect(fn () => $dispatcher->assertDispatched($class))->not->toThrow(AssertionFailedException::class);
});

it('throws AssertionFailedException when asserting dispatched event that was not dispatched', function () {
    $dispatcher = new FakeEventDispatcher();

    expect(fn () => $dispatcher->assertDispatched('SomeEvent'))
        ->toThrow(AssertionFailedException::class, 'Expected [SomeEvent] to be dispatched but it was not.');
});

it('asserts event was not dispatched', function () {
    $dispatcher = new FakeEventDispatcher();

    expect(fn () => $dispatcher->assertNotDispatched('SomeEvent'))->not->toThrow(AssertionFailedException::class);
});

it('throws AssertionFailedException when asserting not dispatched event that was dispatched', function () {
    $dispatcher = new FakeEventDispatcher();
    $event = new class () extends Event {};
    $class = $event::class;

    $dispatcher->dispatch($event);

    expect(fn () => $dispatcher->assertNotDispatched($class))
        ->toThrow(AssertionFailedException::class, "Expected [$class] NOT to be dispatched but it was.");
});

it('asserts dispatched count for a specific event class', function () {
    $dispatcher = new FakeEventDispatcher();
    $event = new class () extends Event {};
    $class = $event::class;

    $dispatcher->dispatch($event);
    $dispatcher->dispatch($event);

    expect(fn () => $dispatcher->assertDispatchedCount($class, 2))->not->toThrow(AssertionFailedException::class);
    expect(fn () => $dispatcher->assertDispatchedCount($class, 3))
        ->toThrow(AssertionFailedException::class, 'Expected 3');
});

it('clears all captured events', function () {
    $dispatcher = new FakeEventDispatcher();
    $event = new class () extends Event {};

    $dispatcher->dispatch($event);
    $dispatcher->clear();

    expect($dispatcher->dispatched)->toBeEmpty();
});

it('returns dispatched events filtered by event class', function () {
    $dispatcher = new FakeEventDispatcher();

    $eventA = new class () extends Event {};
    $eventB = new class () extends Event {};
    $classA = $eventA::class;

    $dispatcher->dispatch($eventA);
    $dispatcher->dispatch($eventB);
    $dispatcher->dispatch($eventA);

    $filtered = $dispatcher->dispatched($classA);

    expect($filtered)->toHaveCount(2)
        ->and($filtered[0])->toBe($eventA)
        ->and($filtered[1])->toBe($eventA);
});
