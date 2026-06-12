<?php

declare(strict_types=1);

namespace Marko\Mail\Smtp\Tests\Unit;

use Marko\Mail\Config\MailConfig;
use Marko\Mail\Smtp\SmtpConfig;
use Marko\Testing\Fake\FakeConfigRepository;

final class Helpers
{
    /**
     * @param array<string> $responses
     */
    public static function createMockSocket(
        array $responses,
        bool $tlsSuccess = true,
    ): MockSocket {
        return new MockSocket($responses, $tlsSuccess);
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function createSmtpConfig(
        array $config,
    ): SmtpConfig {
        $configRepo = new FakeConfigRepository(['mail.smtp' => $config]);
        $mailConfig = new MailConfig($configRepo);

        return new SmtpConfig($mailConfig);
    }
}
