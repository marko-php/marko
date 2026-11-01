<?php

declare(strict_types=1);

namespace Marko\Authentication\Command;

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Contracts\PasswordResetTokenRepositoryInterface;
use Marko\Authentication\Exceptions\AuthException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Psr\Clock\ClockInterface;

/** @noinspection PhpUnused */
#[Command(
    name: 'auth:clear-reset-tokens',
    description: 'Delete password reset tokens older than authentication.password_reset.expire_seconds',
)]
readonly class ClearResetTokensCommand implements CommandInterface
{
    public function __construct(
        private PasswordResetTokenRepositoryInterface $passwordResetTokenRepository,
        private AuthConfig $authConfig,
        private ClockInterface $clock,
    ) {}

    /**
     * @throws AuthException|ConfigNotFoundException
     */
    public function execute(
        Input $input,
        Output $output,
    ): int {
        $before = $this->clock->now()->modify('-' . $this->authConfig->passwordResetExpireSeconds() . ' seconds');
        $count = $this->passwordResetTokenRepository->deleteExpired($before);

        if ($count === 0) {
            $output->writeLine('No expired password reset tokens to clear.');
        } else {
            $output->writeLine("Cleared $count expired password reset token(s).");
        }

        return 0;
    }
}
