<?php

declare(strict_types=1);

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\TransportException;
use Marko\Mail\Message;
use Marko\Notification\Channel\MailChannel;
use Marko\Notification\Contracts\ChannelInterface;
use Marko\Notification\Contracts\NotifiableInterface;
use Marko\Notification\Contracts\NotificationInterface;
use Marko\Notification\Exceptions\ChannelException;

test('it implements ChannelInterface', function (): void {
    $reflection = new ReflectionClass(MailChannel::class);

    expect($reflection->implementsInterface(ChannelInterface::class))->toBeTrue();
});

test('it sends notification mail message via mailer', function (): void {
    $message = Message::create()->to('user@example.com')->subject('Test');

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects($this->once())
        ->method('send')
        ->with($this->callback(fn (Message $msg) => $msg->subject === 'Test'))
        ->willReturn(true);

    $notifiable = $this->createMock(NotifiableInterface::class);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toMail')->willReturn($message);

    $channel = new MailChannel($mailer);
    $channel->send($notifiable, $notification);
});

test('it resolves recipient from notifiable when message has no to address', function (): void {
    $message = Message::create()->subject('Test');

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects($this->once())
        ->method('send')
        ->with($this->callback(fn (Message $msg) => $msg->to[0]->email === 'resolved@example.com'))
        ->willReturn(true);

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('routeNotificationFor')
        ->with('mail')
        ->willReturn('resolved@example.com');

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toMail')->willReturn($message);

    $channel = new MailChannel($mailer);
    $channel->send($notifiable, $notification);
});

test('it uses message to address when already set', function (): void {
    $message = Message::create()->to('preset@example.com')->subject('Test');

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->expects($this->once())
        ->method('send')
        ->with(
            $this->callback(fn (Message $msg) => $msg->to[0]->email === 'preset@example.com' && count($msg->to) === 1)
        )
        ->willReturn(true);

    $notifiable = $this->createMock(NotifiableInterface::class);
    // routeNotificationFor should NOT be called when to is already set
    $notifiable->expects($this->never())->method('routeNotificationFor');

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toMail')->willReturn($message);

    $channel = new MailChannel($mailer);
    $channel->send($notifiable, $notification);
});

test('it throws ChannelException when notifiable has no mail route', function (): void {
    $message = Message::create()->subject('Test');

    $mailer = $this->createMock(MailerInterface::class);

    $notifiable = $this->createMock(NotifiableInterface::class);
    $notifiable->method('routeNotificationFor')->with('mail')->willReturn(null);
    $notifiable->method('getNotifiableType')->willReturn('App\\Entity\\User');

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toMail')->willReturn($message);

    $channel = new MailChannel($mailer);
    $channel->send($notifiable, $notification);
})->throws(ChannelException::class, "No routing information for channel 'mail'.");

test('it throws ChannelException when mailer transport fails', function (): void {
    $message = Message::create()->to('user@example.com')->subject('Test');

    $mailer = $this->createMock(MailerInterface::class);
    $mailer->method('send')->willThrowException(
        TransportException::connectionFailed('smtp.example.com', 587),
    );

    $notifiable = $this->createMock(NotifiableInterface::class);

    $notification = $this->createMock(NotificationInterface::class);
    $notification->method('toMail')->willReturn($message);

    $channel = new MailChannel($mailer);
    $channel->send($notifiable, $notification);
})->throws(ChannelException::class, "Failed to deliver notification via 'mail' channel.");
