<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Fixtures;

use Marko\Authentication\CanResetPasswordInterface;
use Marko\Authentication\Contracts\ResetLinkNotifierInterface;

/**
 * Records every reset link it is asked to send.
 */
class RecordingResetLinkNotifier implements ResetLinkNotifierInterface
{
    /** @var list<array{user: CanResetPasswordInterface, token: string}> */
    public array $sent = [];

    public function send(
        CanResetPasswordInterface $user,
        string $token,
    ): void {
        $this->sent[] = ['user' => $user, 'token' => $token];
    }
}
