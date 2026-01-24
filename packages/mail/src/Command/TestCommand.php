<?php

declare(strict_types=1);

namespace Marko\Mail\Command;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Mail\Config\MailConfig;
use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\TransportException;
use Marko\Mail\Message;

/** @noinspection PhpUnused */
#[Command(name: 'mail:test', description: 'Send a test email to verify mail configuration')]
class TestCommand implements CommandInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly MailConfig $config,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        $email = $input->getArgument(0);

        if ($email === null) {
            $output->writeLine('Error: Email address is required.');
            $output->writeLine('Usage: mail:test <email>');

            return 1;
        }

        $output->writeLine("Sending test email to $email...");

        $subject = $this->parseSubjectOption($input) ?? 'Test Email from Marko';

        $message = Message::create()
            ->to($email)
            ->from($this->config->fromAddress(), $this->config->fromName())
            ->subject($subject)
            ->text('This is a test email sent from Marko to verify your mail configuration is working correctly.')
            ->html(
                '<p>This is a test email sent from Marko to verify your mail configuration is working correctly.</p>',
            );

        try {
            $this->mailer->send($message);
        } catch (TransportException $e) {
            $output->writeLine("Failed to send email: {$e->getMessage()}");

            return 1;
        }

        $output->writeLine('Email sent successfully!');

        return 0;
    }

    private function parseSubjectOption(
        Input $input,
    ): ?string {
        foreach ($input->getArguments() as $arg) {
            if (str_starts_with($arg, '--subject=')) {
                return substr($arg, 10);
            }
        }

        return null;
    }
}
