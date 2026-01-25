<?php

declare(strict_types=1);

namespace Marko\Blog\Commands;

use Marko\Blog\Config\BlogConfigInterface;
use Marko\Blog\Services\TokenRepositoryInterface;
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'blog:cleanup', description: 'Clean up expired verification tokens')]
class CleanupCommand implements CommandInterface
{
    public function __construct(
        private readonly TokenRepositoryInterface $tokenRepository,
        private readonly BlogConfigInterface $config,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $verbose = $input->hasOption('verbose');
        $expiryDays = $this->config->getVerificationTokenExpiryDays();
        $cookieDays = $this->config->getVerificationCookieDays();

        if ($verbose) {
            $output->writeLine(sprintf('Email token expiry: %d days', $expiryDays));
            $output->writeLine(sprintf('Browser token expiry: %d days', $cookieDays));
        }

        $emailTokensDeleted = $this->tokenRepository->deleteExpiredEmailTokens($expiryDays);
        $browserTokensDeleted = $this->tokenRepository->deleteExpiredBrowserTokens($cookieDays);

        $output->writeLine(sprintf('%d email verification token(s) deleted.', $emailTokensDeleted));
        $output->writeLine(sprintf('%d browser token(s) deleted.', $browserTokensDeleted));

        return 0;
    }
}
