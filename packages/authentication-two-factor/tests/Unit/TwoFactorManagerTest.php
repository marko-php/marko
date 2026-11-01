<?php

declare(strict_types=1);

use Marko\Authentication\Config\AuthConfig;
use Marko\Authentication\Event\OtherDevicesLoggedOutEvent;
use Marko\Authentication\Exceptions\UserPersisterException;
use Marko\AuthenticationTwoFactor\Contracts\TwoFactorManagerInterface;
use Marko\AuthenticationTwoFactor\Event\RecoveryCodeUsedEvent;
use Marko\AuthenticationTwoFactor\Event\TwoFactorDisabledEvent;
use Marko\AuthenticationTwoFactor\Event\TwoFactorEnabledEvent;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\AuthenticationTwoFactor\Tests\Fixtures\TwoFactorHarness;
use Marko\Core\Container\Container;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeUserPersister;

it('implements TwoFactorManagerInterface', function (): void {
    expect(new TwoFactorHarness()->manager())->toBeInstanceOf(TwoFactorManagerInterface::class);
});

describe('enable', function (): void {
    it('stores a new secret and hashed recovery codes without activating two-factor', function (): void {
        $harness = new TwoFactorHarness();

        $setup = $harness->manager()->enable($harness->user, 'ada@example.com');

        expect($harness->user->getTwoFactorSecret())->toBe($setup->secret)
            ->and($setup->secret)->toMatch('/^[A-Z2-7]{32}$/')
            ->and($harness->user->getTwoFactorConfirmedAt())->toBeNull()
            ->and($harness->manager()->isEnabled($harness->user))->toBeFalse()
            ->and($setup->recoveryCodes)->toHaveCount(8)
            ->and(array_unique($setup->recoveryCodes))->toHaveCount(8)
            ->and($harness->user->getTwoFactorRecoveryCodes())
            ->toBe(array_map(fn (string $code): string => hash('sha256', $code), $setup->recoveryCodes))
            ->and($harness->persister->persisted())->toBe([$harness->user])
            ->and($harness->events->dispatched)->toBeEmpty();
    });

    it('returns an otpauth URI with the label and configured issuer', function (): void {
        $harness = new TwoFactorHarness();

        $setup = $harness->manager()->enable($harness->user, 'ada@example.com');

        expect($setup->provisioningUri)->toStartWith('otpauth://totp/Acme%3Aada%40example.com?')
            ->and($setup->provisioningUri)->toContain('secret=' . $setup->secret);
    });

    it('issues the configured number of recovery codes', function (): void {
        $harness = new TwoFactorHarness(['authentication-two-factor.recovery_code_count' => 3]);

        expect($harness->manager()->enable($harness->user, 'ada@example.com')->recoveryCodes)->toHaveCount(3);
    });

    it('can be restarted while setup is unconfirmed', function (): void {
        $harness = new TwoFactorHarness();
        $first = $harness->manager()->enable($harness->user, 'ada@example.com');

        $second = $harness->manager()->enable($harness->user, 'ada@example.com');

        expect($second->secret)->not->toBe($first->secret)
            ->and($harness->user->getTwoFactorSecret())->toBe($second->secret);
    });

    it('refuses to replace an active secret', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();

        $harness->manager()->enable($harness->user, 'ada@example.com');
    })->throws(TwoFactorException::class, "Two-factor authentication is already enabled for user '7'");

    it('throws when the issuer is not configured', function (): void {
        $harness = new TwoFactorHarness(['authentication-two-factor.issuer' => null]);

        $harness->manager()->enable($harness->user, 'ada@example.com');
    })->throws(TwoFactorException::class, 'authentication-two-factor.issuer is not set');

    it('fails loudly when no persister can save the user', function (): void {
        $harness = new TwoFactorHarness();
        $harness->container = new Container();

        $harness->manager()->enable($harness->user, 'ada@example.com');
    })->throws(UserPersisterException::class);
});

describe('confirm', function (): void {
    it('activates two-factor with a valid first code and dispatches TwoFactorEnabledEvent', function (): void {
        $harness = new TwoFactorHarness();
        $harness->manager()->enable($harness->user, 'ada@example.com');
        $harness->persister->clear();

        $confirmed = $harness->manager()->confirm($harness->user, $harness->currentCode());

        expect($confirmed)->toBeTrue()
            ->and($harness->manager()->isEnabled($harness->user))->toBeTrue()
            ->and($harness->user->getTwoFactorConfirmedAt()?->getTimestamp())->toBe(1700000000)
            ->and($harness->user->getTwoFactorLastUsedStep())->toBe(intdiv(1700000000, 30))
            ->and($harness->persister->persisted())->toBe([$harness->user])
            ->and($harness->events->dispatched(TwoFactorEnabledEvent::class))->toHaveCount(1);
    });

    it('ends the user\'s other remember-me devices when two-factor becomes active', function (): void {
        $harness = new TwoFactorHarness();
        $harness->manager()->enable($harness->user, 'ada@example.com');
        $harness->user->setRememberToken('hash-from-another-device');
        $harness->user->setRememberTokenExpiresAt(new DateTimeImmutable('+30 days'));

        $harness->manager()->confirm($harness->user, $harness->currentCode());

        $loggedOut = $harness->events->dispatched(OtherDevicesLoggedOutEvent::class);
        expect($harness->user->getRememberToken())->toBeNull()
            ->and($loggedOut)->toHaveCount(1)
            ->and($loggedOut[0]->user)->toBe($harness->user);
    });

    it('rejects a wrong code and leaves two-factor inactive', function (): void {
        $harness = new TwoFactorHarness();
        $harness->manager()->enable($harness->user, 'ada@example.com');
        $harness->persister->clear();

        expect($harness->manager()->confirm($harness->user, '000000'))->toBeFalse()
            ->and($harness->manager()->isEnabled($harness->user))->toBeFalse()
            ->and($harness->persister->persisted())->toBeEmpty()
            ->and($harness->events->dispatched)->toBeEmpty();
    });

    it('throws when enable() was never called', function (): void {
        $harness = new TwoFactorHarness();

        $harness->manager()->confirm($harness->user, '123456');
    })->throws(TwoFactorException::class, "User '7' has no two-factor secret to confirm");

    it('throws when two-factor is already confirmed', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();

        $harness->manager()->confirm($harness->user, $harness->currentCode());
    })->throws(TwoFactorException::class, 'already enabled');
});

describe('verify', function (): void {
    it('accepts a current TOTP code and stores its step', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();

        expect($harness->manager()->verify($harness->user, $harness->currentCode()))->toBeTrue()
            ->and($harness->user->getTwoFactorLastUsedStep())->toBe(intdiv(1700000030, 30))
            ->and($harness->persister->persisted())->toBe([$harness->user]);
    });

    it('rejects a replayed TOTP code', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $code = $harness->currentCode();
        $harness->manager()->verify($harness->user, $code);

        expect($harness->manager()->verify($harness->user, $code))->toBeFalse();
    });

    it('accepts a recovery code once and stores only the remaining hashes', function (): void {
        $harness = new TwoFactorHarness();
        $codes = $harness->enableTwoFactor();

        expect($harness->manager()->verify($harness->user, $codes[2]))->toBeTrue()
            ->and($harness->user->getTwoFactorRecoveryCodes())->toHaveCount(7)
            ->and($harness->user->getTwoFactorRecoveryCodes())->not->toContain(hash('sha256', $codes[2]))
            ->and($harness->user->getTwoFactorRecoveryCodes())->not->toContain($codes[0])
            ->and($harness->persister->persisted())->toBe([$harness->user])
            ->and($harness->manager()->verify($harness->user, $codes[2]))->toBeFalse();
    });

    it('accepts a recovery code typed in upper case or with surrounding spaces', function (): void {
        $harness = new TwoFactorHarness();
        $codes = $harness->enableTwoFactor();

        expect($harness->manager()->verify($harness->user, '  ' . strtoupper($codes[0]) . ' '))->toBeTrue();
    });

    it('dispatches RecoveryCodeUsedEvent with the remaining count', function (): void {
        $harness = new TwoFactorHarness();
        $codes = $harness->enableTwoFactor();

        $harness->manager()->verify($harness->user, $codes[0]);

        $events = $harness->events->dispatched(RecoveryCodeUsedEvent::class);
        expect($events)->toHaveCount(1)
            ->and($events[0]->remainingCodes)->toBe(7)
            ->and($events[0]->user)->toBe($harness->user);
    });

    it('rejects an unknown code without persisting', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();

        expect($harness->manager()->verify($harness->user, 'abcde-12345'))->toBeFalse()
            ->and($harness->persister->persisted())->toBeEmpty();
    });

    it('throws for a user without two-factor enabled', function (): void {
        $harness = new TwoFactorHarness();

        $harness->manager()->verify($harness->user, '123456');
    })->throws(TwoFactorException::class, "Two-factor authentication is not enabled for user '7'");
});

describe('regenerateRecoveryCodes', function (): void {
    it('replaces every recovery code', function (): void {
        $harness = new TwoFactorHarness();
        $old = $harness->enableTwoFactor();

        $new = $harness->manager()->regenerateRecoveryCodes($harness->user);

        expect($new)->toHaveCount(8)
            ->and(array_intersect($new, $old))->toBeEmpty()
            ->and($harness->user->getTwoFactorRecoveryCodes())
            ->toBe(array_map(fn (string $code): string => hash('sha256', $code), $new))
            ->and($harness->persister->persisted())->toBe([$harness->user])
            ->and($harness->manager()->verify($harness->user, $old[0]))->toBeFalse();
    });

    it('throws for a user without two-factor enabled', function (): void {
        $harness = new TwoFactorHarness();

        $harness->manager()->regenerateRecoveryCodes($harness->user);
    })->throws(TwoFactorException::class, 'not enabled');
});

describe('disable', function (): void {
    it('clears the two-factor state and dispatches TwoFactorDisabledEvent', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();

        $harness->manager()->disable($harness->user);

        expect($harness->user->getTwoFactorSecret())->toBeNull()
            ->and($harness->user->getTwoFactorRecoveryCodes())->toBe([])
            ->and($harness->user->getTwoFactorConfirmedAt())->toBeNull()
            ->and($harness->user->getTwoFactorLastUsedStep())->toBeNull()
            ->and($harness->manager()->isEnabled($harness->user))->toBeFalse()
            ->and($harness->persister->persisted())->toBe([$harness->user])
            ->and($harness->events->dispatched(TwoFactorDisabledEvent::class))->toHaveCount(1);
    });

    it('does not dispatch TwoFactorDisabledEvent when two-factor was never confirmed', function (): void {
        $harness = new TwoFactorHarness();
        $harness->manager()->enable($harness->user, 'ada@example.com');

        $harness->manager()->disable($harness->user);

        expect($harness->user->getTwoFactorSecret())->toBeNull()
            ->and($harness->events->dispatched(TwoFactorDisabledEvent::class))->toBeEmpty();
    });
});

describe('persistence per guard', function (): void {
    it('saves through the persister of the guard user provider', function (): void {
        $harness = new TwoFactorHarness();
        $harness->authConfig = new AuthConfig(new FakeConfigRepository([
            'authentication.default.guard' => 'web',
            'authentication.default.provider' => 'users',
            'authentication.guards' => [
                'web' => ['driver' => 'session'],
                'admin' => ['driver' => 'session', 'provider' => 'admins'],
            ],
            'authentication.providers' => [
                'users' => [],
                'admins' => ['persister' => AdminUserPersister::class],
            ],
        ]));
        $harness->container->instance(AdminUserPersister::class, new AdminUserPersister());

        $harness->manager()->enable($harness->user, 'ada@example.com', 'admin');

        expect($harness->persister->persisted())->toBeEmpty()
            ->and($harness->container->get(AdminUserPersister::class)->persisted())->toBe([$harness->user]);
    });
});

class AdminUserPersister extends FakeUserPersister {}
