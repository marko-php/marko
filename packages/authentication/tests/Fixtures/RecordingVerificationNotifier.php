<?php

declare(strict_types=1);

namespace Marko\Authentication\Tests\Fixtures;

use Marko\Authentication\Contracts\VerificationNotifierInterface;
use Marko\Authentication\MustVerifyEmailInterface;

/**
 * Records every verification link it is asked to send.
 */
class RecordingVerificationNotifier implements VerificationNotifierInterface
{
    /** @var list<array{user: MustVerifyEmailInterface, url: string}> */
    public array $sent = [];

    public function send(
        MustVerifyEmailInterface $user,
        string $url,
    ): void {
        $this->sent[] = ['user' => $user, 'url' => $url];
    }
}
