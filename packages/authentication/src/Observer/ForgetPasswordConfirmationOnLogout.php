<?php

declare(strict_types=1);

namespace Marko\Authentication\Observer;

use Marko\Authentication\Contracts\PasswordConfirmationInterface;
use Marko\Authentication\Event\LogoutEvent;
use Marko\Core\Attributes\Observer;
use Marko\Session\Contracts\SessionInterface;

/**
 * A logout keeps the session's data, so drop the password confirmation with the
 * user it belonged to.
 */
#[Observer(event: LogoutEvent::class)]
readonly class ForgetPasswordConfirmationOnLogout
{
    public function __construct(
        private SessionInterface $session,
    ) {}

    public function handle(
        LogoutEvent $event,
    ): void {
        $this->session->remove(PasswordConfirmationInterface::SESSION_KEY);
    }
}
