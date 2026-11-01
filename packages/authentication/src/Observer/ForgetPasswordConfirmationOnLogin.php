<?php

declare(strict_types=1);

namespace Marko\Authentication\Observer;

use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Event\LoginEvent;
use Marko\Core\Attributes\Observer;
use Marko\Session\Contracts\SessionInterface;

/**
 * A login keeps the session's data, so drop any earlier password confirmation:
 * it belonged to whoever was signed in before.
 */
#[Observer(event: LoginEvent::class)]
readonly class ForgetPasswordConfirmationOnLogin
{
    public function __construct(
        private SessionInterface $session,
    ) {}

    public function handle(
        LoginEvent $event,
    ): void {
        $this->session->remove(PasswordConfirmationInterface::SESSION_KEY);
    }
}
