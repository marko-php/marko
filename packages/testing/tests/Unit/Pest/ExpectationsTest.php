<?php

declare(strict_types=1);

use Marko\Core\Event\Event;
use Marko\Mail\Message;
use Marko\Queue\Job;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeClock;
use Marko\Testing\Fake\FakeEventDispatcher;
use Marko\Testing\Fake\FakeFilesystem;
use Marko\Testing\Fake\FakeHttpClient;
use Marko\Testing\Fake\FakeLogger;
use Marko\Testing\Fake\FakeMailer;
use Marko\Testing\Fake\FakeNotificationSender;
use Marko\Testing\Fake\FakeQueue;
use Marko\Testing\Fake\FakeUserPersister;
use Marko\Testing\Fake\Http\RecordedRequest;
use Marko\Testing\Tests\Unit\Fake\NotificationFixtures\FixtureNotifiable;
use Marko\Testing\Tests\Unit\Fake\NotificationFixtures\FixtureOrderShipped;
use PHPUnit\Framework\ExpectationFailedException;

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

it('registers toHaveSentRequest expectation for FakeHttpClient', function () {
    $http = new FakeHttpClient();
    $http->preventStrayRequests(false);

    expect($http)->not->toHaveSentRequest();

    $http->get('https://api.example.com/orders');

    expect($http)->toHaveSentRequest();
});

it('matches toHaveSentRequest against a callback', function () {
    $http = new FakeHttpClient();
    $http->preventStrayRequests(false);

    $http->post('https://api.example.com/orders', ['json' => ['id' => 1]]);

    expect($http)->toHaveSentRequest(fn (RecordedRequest $r) => $r->method === 'POST' && $r->json()['id'] === 1)
        ->not->toHaveSentRequest(fn (RecordedRequest $r) => $r->method === 'DELETE');
});

it('fails toHaveSentRequest when no request matches', function () {
    $http = new FakeHttpClient();

    expect(fn () => expect($http)->toHaveSentRequest())
        ->toThrow(ExpectationFailedException::class, 'Expected at least one HTTP request to be sent');
});

it('throws clear error when toHaveSentRequest is used on wrong type', function () {
    expect(fn () => expect(new stdClass())->toHaveSentRequest())
        ->toThrow(InvalidArgumentException::class, 'Expected FakeHttpClient');
});

it('registers toHaveStored expectation for FakeFilesystem', function () {
    $filesystem = new FakeFilesystem(clock: new FakeClock(), public: false);

    $filesystem->write('avatars/1.png', 'png-bytes');

    expect($filesystem)->toHaveStored('avatars/1.png')
        ->toHaveStored('/avatars/1.png', 'png-bytes')
        ->not->toHaveStored('avatars/2.png')
        ->not->toHaveStored('avatars/1.png', 'other-bytes')
        ->not->toHaveStored('avatars');
});

it('fails toHaveStored when nothing is stored at the path', function () {
    $filesystem = new FakeFilesystem(clock: new FakeClock(), public: false);

    expect(fn () => expect($filesystem)->toHaveStored('avatars/1.png'))
        ->toThrow(ExpectationFailedException::class, "Expected a file to be stored at 'avatars/1.png' but none was.");
});

it('fails toHaveStored when the stored contents differ', function () {
    $filesystem = new FakeFilesystem(clock: new FakeClock(), public: false);
    $filesystem->write('a.txt', 'actual');

    expect(fn () => expect($filesystem)->toHaveStored('a.txt', 'expected'))
        ->toThrow(
            ExpectationFailedException::class,
            "Expected a file with the given contents to be stored at 'a.txt'",
        );
});

it('throws clear error when toHaveStored is used on wrong type', function () {
    expect(fn () => expect(new stdClass())->toHaveStored('a.txt'))
        ->toThrow(InvalidArgumentException::class, 'Expected FakeFilesystem');
});

it('throws clear error when expectation used on wrong type', function () {
    $notADispatcher = new stdClass();

    expect(fn () => expect($notADispatcher)->toHaveDispatched('SomeEvent'))
        ->toThrow(InvalidArgumentException::class, 'Expected FakeEventDispatcher');
});

it('registers toHaveNotified expectation for FakeNotificationSender', function () {
    $sender = new FakeNotificationSender();
    $sender->send(new FixtureNotifiable(1), new FixtureOrderShipped('TRK-9'));

    expect($sender)->toHaveNotified(new FixtureNotifiable(1), FixtureOrderShipped::class)
        ->toHaveNotified(
            new FixtureNotifiable(1),
            FixtureOrderShipped::class,
            fn (FixtureOrderShipped $sent, array $channels) => $sent->trackingNumber === 'TRK-9'
                && $channels === ['mail', 'database'],
        )
        ->not->toHaveNotified(new FixtureNotifiable(2), FixtureOrderShipped::class);
});

it('fails toHaveNotified when the notification was only queued', function () {
    $sender = new FakeNotificationSender();
    $sender->queue(new FixtureNotifiable(1), new FixtureOrderShipped());

    expect(fn () => expect($sender)->toHaveNotified(new FixtureNotifiable(1), FixtureOrderShipped::class))
        ->toThrow(ExpectationFailedException::class, 'to be sent to App\\User#1');
});

it('throws clear error when toHaveNotified is used on wrong type', function () {
    expect(fn () => expect(new stdClass())->toHaveNotified(new FixtureNotifiable(1), FixtureOrderShipped::class))
        ->toThrow(InvalidArgumentException::class, 'Expected FakeNotificationSender');
});

it('registers toHavePersisted expectation for FakeUserPersister', function () {
    $persister = new FakeUserPersister();

    expect($persister)->not->toHavePersisted();

    $persister->persist(new FakeAuthenticatable(id: 1));

    expect($persister)->toHavePersisted()
        ->toHavePersisted(new FakeAuthenticatable(id: 1))
        ->not->toHavePersisted(new FakeAuthenticatable(id: 2));
});

it('fails toHavePersisted with the missing user in the message', function () {
    $persister = new FakeUserPersister();

    expect(fn () => expect($persister)->toHavePersisted(new FakeAuthenticatable(id: 3)))
        ->toThrow(ExpectationFailedException::class, FakeAuthenticatable::class . '#3] to be persisted');
});

it('throws clear error when toHavePersisted is used on wrong type', function () {
    expect(fn () => expect(new stdClass())->toHavePersisted())
        ->toThrow(InvalidArgumentException::class, 'Expected FakeUserPersister');
});
