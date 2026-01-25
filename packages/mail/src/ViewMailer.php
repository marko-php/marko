<?php

declare(strict_types=1);

namespace Marko\Mail;

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Contracts\ViewInterface;

/**
 * Decorator that adds view template rendering to any mailer.
 *
 * When a Message has a view template set, this mailer renders the template
 * to HTML before delegating to the underlying mailer. If no ViewInterface
 * is provided, messages pass through unchanged.
 */
readonly class ViewMailer implements MailerInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private ?ViewInterface $view = null,
    ) {}

    public function send(
        Message $message,
    ): bool {
        if ($this->view !== null && $message->view !== null) {
            $html = $this->view->render(
                $message->view,
                $message->viewData,
            );
            $message->html($html);
        }

        return $this->mailer->send($message);
    }

    public function sendRaw(
        string $to,
        string $raw,
    ): bool {
        return $this->mailer->sendRaw($to, $raw);
    }
}
