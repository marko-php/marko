<?php

declare(strict_types=1);

use Marko\Authentication\Exceptions\AuthException;
use Marko\Authentication\Exceptions\UnsupportedAuthFeatureException;
use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;

describe('guard capability exceptions', function (): void {
    it(
        'makes the guard capability exceptions AuthExceptions and UnsupportedFeatureExceptionInterfaces',
        function (): void {
            foreach ([
                AuthException::otherDeviceLogoutUnsupported('api', 'TokenGuard'),
                AuthException::challengesNotSupported('api', 'token'),
            ] as $exception) {
                expect($exception)
                    ->toBeInstanceOf(UnsupportedAuthFeatureException::class)
                    ->toBeInstanceOf(AuthException::class)
                    ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class);
            }
        },
    );

    it('reports the feature and driver of each capability exception', function (): void {
        $logout = AuthException::otherDeviceLogoutUnsupported('api', 'TokenGuard');
        $challenges = AuthException::challengesNotSupported('api', 'token');

        expect($logout->getFeature())->toBe('logoutOtherDevices()')
            ->and($logout->getDriver())->toBe('TokenGuard')
            ->and($challenges->getFeature())->toBe('login challenges')
            ->and($challenges->getDriver())->toBe('token');
    });

    it('keeps the existing exception messages, contexts and suggestions', function (): void {
        $logout = AuthException::otherDeviceLogoutUnsupported('api', 'TokenGuard');
        $challenges = AuthException::challengesNotSupported('api', 'token');

        expect($logout->getMessage())->toBe("Guard 'api' cannot log out other devices")
            ->and($logout->getContext())->toContain('TokenGuard does not implement')
            ->and($logout->getSuggestion())->toContain('Call logoutOtherDevices() on a session guard')
            ->and($challenges->getMessage())->toBe(
                "Guard 'api' lists login challenges, but its 'token' driver does not support them",
            )
            ->and($challenges->getContext())->toContain('authentication.guards.api.challenges is set')
            ->and($challenges->getSuggestion())->toContain("or use the 'session' driver");
    });
});
