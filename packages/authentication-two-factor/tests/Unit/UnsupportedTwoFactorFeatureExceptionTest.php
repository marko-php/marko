<?php

declare(strict_types=1);

use Marko\AuthenticationTwoFactor\Exceptions\TwoFactorException;
use Marko\AuthenticationTwoFactor\Exceptions\UnsupportedTwoFactorFeatureException;
use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;

describe('guardNotChallengeable', function (): void {
    it(
        'makes the two-factor guard not challengeable exception a TwoFactorException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exception = TwoFactorException::guardNotChallengeable('api');

            expect($exception)
                ->toBeInstanceOf(UnsupportedTwoFactorFeatureException::class)
                ->toBeInstanceOf(TwoFactorException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class);
        },
    );

    it('reports the feature and driver of each capability exception', function (): void {
        $exception = TwoFactorException::guardNotChallengeable('api');

        expect($exception->getFeature())->toBe('login challenges')
            ->and($exception->getDriver())->toBe('api');
    });

    it('keeps the existing exception messages, contexts and suggestions', function (): void {
        $exception = TwoFactorException::guardNotChallengeable('api');

        expect($exception->getMessage())->toBe("Guard 'api' does not support login challenges")
            ->and($exception->getContext())->toBe(
                'Two-factor login needs a guard that implements ChallengeableGuardInterface, such as the session guard',
            )
            ->and($exception->getSuggestion())->toBe(
                "Use a 'session' guard for two-factor logins, or pass the right guard name",
            );
    });
});
