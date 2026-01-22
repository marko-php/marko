<?php

declare(strict_types=1);

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Contracts\ViewInterface;
use Marko\Mail\Message;
use Marko\Mail\ViewMailer;

describe('ViewMailer', function (): void {
    it('renders template when ViewInterface available', function (): void {
        $mailer = $this->createMock(MailerInterface::class);
        $view = $this->createMock(ViewInterface::class);

        $view->expects($this->once())
            ->method('render')
            ->with('emails.welcome', ['name' => 'John'])
            ->willReturn('<h1>Welcome John!</h1>');

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Message $message) {
                return $message->getHtml() === '<h1>Welcome John!</h1>';
            }))
            ->willReturn(true);

        $viewMailer = new ViewMailer($mailer, $view);

        $message = Message::create()
            ->to('user@example.com')
            ->subject('Welcome!')
            ->view('emails.welcome')
            ->with(['name' => 'John']);

        $result = $viewMailer->send($message);

        expect($result)->toBeTrue();
    });

    it('works without view package installed', function (): void {
        $mailer = $this->createMock(MailerInterface::class);

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Message $message) {
                // HTML should remain unchanged when no ViewInterface provided
                return $message->getHtml() === '<h1>Welcome!</h1>';
            }))
            ->willReturn(true);

        // No ViewInterface provided - simulates view package not installed
        $viewMailer = new ViewMailer($mailer);

        $message = Message::create()
            ->to('user@example.com')
            ->subject('Welcome!')
            ->html('<h1>Welcome!</h1>');

        $result = $viewMailer->send($message);

        expect($result)->toBeTrue();
    });

    it('passes through message with view template when ViewInterface not available', function (): void {
        $mailer = $this->createMock(MailerInterface::class);

        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function (Message $message) {
                // Template data is stored but not rendered
                return $message->getView() === 'emails.welcome'
                    && $message->getViewData() === ['name' => 'John']
                    && $message->getHtml() === null;
            }))
            ->willReturn(true);

        // No ViewInterface provided
        $viewMailer = new ViewMailer($mailer);

        $message = Message::create()
            ->to('user@example.com')
            ->subject('Welcome!')
            ->view('emails.welcome')
            ->with(['name' => 'John']);

        $result = $viewMailer->send($message);

        expect($result)->toBeTrue();
    });

    it('delegates sendRaw to underlying mailer', function (): void {
        $mailer = $this->createMock(MailerInterface::class);
        $view = $this->createMock(ViewInterface::class);

        $mailer->expects($this->once())
            ->method('sendRaw')
            ->with('user@example.com', 'Raw email content')
            ->willReturn(true);

        $viewMailer = new ViewMailer($mailer, $view);

        $result = $viewMailer->sendRaw('user@example.com', 'Raw email content');

        expect($result)->toBeTrue();
    });
});
