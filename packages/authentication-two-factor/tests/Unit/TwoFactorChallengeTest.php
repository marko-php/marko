<?php

declare(strict_types=1);

use Marko\Authentication\Contracts\LoginChallengeInterface;
use Marko\Authentication\Event\LoginEvent;
use Marko\AuthenticationTwoFactor\Event\TwoFactorChallengeFailedEvent;
use Marko\AuthenticationTwoFactor\Exceptions\TooManyTwoFactorAttemptsException;
use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\AuthenticationTwoFactor\Tests\Fixtures\TwoFactorHarness;
use Marko\AuthenticationTwoFactor\TwoFactorLoginChallenge;
use Marko\Testing\Fake\FakeAuthenticatable;

describe('TwoFactorLoginChallenge', function (): void {
    it('is a login challenge named two_factor', function (): void {
        $challenge = new TwoFactorLoginChallenge(new TwoFactorHarness()->manager());

        expect($challenge)->toBeInstanceOf(LoginChallengeInterface::class)
            ->and($challenge->getName())->toBe('two_factor');
    });

    it('requires the challenge only once two-factor is confirmed', function (): void {
        $harness = new TwoFactorHarness();
        $challenge = new TwoFactorLoginChallenge($harness->manager());

        expect($challenge->requiresChallenge($harness->user))->toBeFalse();

        $harness->manager()->enable($harness->user, 'ada@example.com');

        expect($challenge->requiresChallenge($harness->user))->toBeFalse();

        $harness->manager()->confirm($harness->user, $harness->currentCode());

        expect($challenge->requiresChallenge($harness->user))->toBeTrue();
    });

    it('does not challenge users that cannot store two-factor state', function (): void {
        $challenge = new TwoFactorLoginChallenge(new TwoFactorHarness()->manager());

        expect($challenge->requiresChallenge(new FakeAuthenticatable(id: 1)))->toBeFalse();
    });
});

describe('TwoFactorChallenge', function (): void {
    it('holds a user with two-factor enabled at the challenge after the password check', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $challenge = $harness->challenge();

        $result = $harness->authManager()->attempt(['email' => 'ada@example.com', 'password' => 'secret']);

        expect($result)->toBeFalse()
            ->and($challenge->isPending())->toBeTrue()
            ->and($harness->authManager()->check())->toBeFalse()
            ->and($harness->events->dispatched(LoginEvent::class))->toBeEmpty();
    });

    it('lets a user without two-factor straight in', function (): void {
        $harness = new TwoFactorHarness();

        expect($harness->authManager()->attempt(['email' => 'ada@example.com']))->toBeTrue()
            ->and($harness->challenge()->isPending())->toBeFalse();
    });

    it('completes the login with a valid TOTP code', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $challenge = $harness->challenge();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);

        expect($challenge->verify($harness->currentCode()))->toBeTrue()
            ->and($harness->authManager()->check())->toBeTrue()
            ->and($harness->authManager()->user())->toBe($harness->user)
            ->and($challenge->isPending())->toBeFalse()
            ->and($harness->events->dispatched(LoginEvent::class))->toHaveCount(1);
    });

    it('completes the login with a recovery code, which then stops working', function (): void {
        $harness = new TwoFactorHarness();
        $codes = $harness->enableTwoFactor();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);

        expect($harness->challenge()->verify($codes[0]))->toBeTrue();

        $harness->authManager()->logout();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);

        expect($harness->challenge()->verify($codes[0]))->toBeFalse();
    });

    it('passes remember through to the completed login', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);

        $harness->challenge()->verify($harness->currentCode(), remember: true);

        expect($harness->events->dispatched(LoginEvent::class)[0]->remember)->toBeTrue();
    });

    it('keeps the remember flag given to attempt() when verify() is called without one', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $harness->authManager()->attempt(['email' => 'ada@example.com'], remember: true);

        $harness->challenge()->verify($harness->currentCode());

        $logins = $harness->events->dispatched(LoginEvent::class);

        expect($logins)->toHaveCount(1)
            ->and($logins[0]->remember)->toBeTrue();
    });

    it('lets verify(remember: false) override the remember flag given to attempt()', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $harness->authManager()->attempt(['email' => 'ada@example.com'], remember: true);

        $harness->challenge()->verify($harness->currentCode(), remember: false);

        expect($harness->events->dispatched(LoginEvent::class)[0]->remember)->toBeFalse();
    });

    it('rejects a wrong code and dispatches TwoFactorChallengeFailedEvent', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $challenge = $harness->challenge();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);

        expect($challenge->verify('000000'))->toBeFalse()
            ->and($challenge->isPending())->toBeTrue()
            ->and($harness->authManager()->check())->toBeFalse();

        $failed = $harness->events->dispatched(TwoFactorChallengeFailedEvent::class);
        expect($failed)->toHaveCount(1)
            ->and($failed[0]->user)->toBe($harness->user)
            ->and($failed[0]->guard)->toBe('web');
    });

    it('throttles attempts per pending user', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $challenge = $harness->challenge();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);

        $challenge->verify('000000');
        $challenge->verify('000000');
        $challenge->verify('000000');

        try {
            // Even the right code is refused while locked out
            $challenge->verify($harness->currentCode());
        } catch (TooManyTwoFactorAttemptsException $exception) {
            expect($exception->getStatusCode())->toBe(429)
                ->and($exception->getRetryAfter())->toBeGreaterThan(0)
                ->and($exception->getHeaders())->toHaveKey('Retry-After')
                ->and($harness->authManager()->check())->toBeFalse();

            return;
        }

        throw new RuntimeException('Expected TooManyTwoFactorAttemptsException');
    });

    it('allows codes again once the throttle window has passed', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $challenge = $harness->challenge();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);
        $challenge->verify('000000');
        $challenge->verify('000000');
        $challenge->verify('000000');

        $harness->clock->travel('+61 seconds');

        expect($challenge->verify($harness->currentCode()))->toBeTrue();
    });

    it('clears the throttle after a successful code', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $challenge = $harness->challenge();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);
        $challenge->verify('000000');
        $challenge->verify('000000');
        $challenge->verify($harness->currentCode());

        $harness->authManager()->logout();
        $harness->clock->travel('+30 seconds');
        $harness->authManager()->attempt(['email' => 'ada@example.com']);
        $challenge->verify('000000');
        $challenge->verify('000000');

        expect($challenge->verify($harness->currentCode()))->toBeTrue();
    });

    it('throws when no two-factor challenge is pending', function (): void {
        $harness = new TwoFactorHarness();

        $harness->challenge()->verify('123456');
    })->throws(TwoFactorException::class, "Guard 'web' has no pending two-factor challenge");

    it('cancels the pending challenge', function (): void {
        $harness = new TwoFactorHarness();
        $harness->enableTwoFactor();
        $challenge = $harness->challenge();
        $harness->authManager()->attempt(['email' => 'ada@example.com']);

        $challenge->cancel();

        expect($challenge->isPending())->toBeFalse()
            ->and($harness->authManager()->check())->toBeFalse();
    });
});
