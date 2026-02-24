<?php

declare(strict_types=1);

use Marko\Core\Event\Event;
use Marko\Mail\Message;
use Marko\Queue\Job;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeLogger;
use Marko\Testing\Fake\FakeMailer;
use Marko\Testing\Fake\FakeQueue;

it('registers toHaveDispatched expectation for FakeEventDispatcher', function () {
    $dispatcher = new FakeEventDispatcher();
    $event = new class () extends Event {};
    $eventClass = $event::class;

    $dispatcher->dispatch($event);

    expect($dispatcher)->toHaveDispatched($eventClass);
});

it('registers toHaveSent expectation for FakeMailer', function () {
    $mailer = new FakeMailer();
    $message = Message::create()->to('test@example.com')->subject('Hello');

    $mailer->send($message);

    expect($mailer)->toHaveSent();
});

it('registers toHaveLogged expectation for FakeLogger', function () {
    $logger = new FakeLogger();

    $logger->info('Something happened');

    expect($logger)->toHaveLogged('Something happened');
});

it('registers toHavePushed expectation for FakeQueue', function () {
    $queue = new FakeQueue();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    $queue->push($job);

    expect($queue)->toHavePushed($job::class);
});

it('provides negated expectations (not->toHaveDispatched, etc.)', function () {
    $dispatcher = new FakeEventDispatcher();
    $mailer = new FakeMailer();
    $queue = new FakeQueue();
    $logger = new FakeLogger();
    $job = new class () extends Job
    {
        public function handle(): void {}
    };

    expect($dispatcher)->not->toHaveDispatched(Event::class)
        ->and($mailer)->not->toHaveSent()
        ->and($logger)->not->toHaveLogged('Nothing logged')
        ->and($queue)->not->toHavePushed($job::class);
});

it('throws clear error when expectation used on wrong type', function () {
    $notADispatcher = new stdClass();

    expect(fn () => expect($notADispatcher)->toHaveDispatched('SomeEvent'))
        ->toThrow(InvalidArgumentException::class, 'Expected FakeEventDispatcher');
});
