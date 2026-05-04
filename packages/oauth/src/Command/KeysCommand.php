<?php

declare(strict_types=1);

namespace Marko\OAuth\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\OAuth\Config\OAuthConfig;
use Marko\OAuth\Exceptions\OAuthException;
use Marko\OAuth\Service\KeyGenerator;

/** @noinspection PhpUnused */
#[Command(name: 'oauth:keys', description: 'Generate OAuth signing keys')]
readonly class KeysCommand implements CommandInterface
{
    public function __construct(
        private OAuthConfig $config,
        private KeyGenerator $keys,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        try {
            $this->keys->generate(
                privateKeyPath: $this->config->privateKeyPath(),
                publicKeyPath: $this->config->publicKeyPath(),
                passphrase: $this->config->keyPassphrase(),
                force: $input->hasOption('force'),
            );
        } catch (OAuthException $exception) {
            $output->writeLine("Error: {$exception->getMessage()}");

            if ($exception->getSuggestion() !== '') {
                $output->writeLine($exception->getSuggestion());
            }

            return 1;
        }

        $output->writeLine('OAuth signing keys generated.');
        $output->writeLine("Private key: {$this->config->privateKeyPath()}");
        $output->writeLine("Public key: {$this->config->publicKeyPath()}");

        return 0;
    }
}
