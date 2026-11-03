<?php

declare(strict_types=1);

use Marko\Core\Contracts\ResettableInterface;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\CsrfTokenManager;
use Marko\Security\Exceptions\CsrfTokenMismatchException;
use Marko\Security\Exceptions\SecurityException;
use Marko\Testing\Fake\FakeEncryptor;
use Marko\Testing\Fake\FakeSession;

describe('CsrfTokenManagerInterface', function (): void {
    it('defines CsrfTokenManagerInterface with get validate and regenerate methods', function (): void {
        $reflection = new ReflectionClass(CsrfTokenManagerInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('get'))->toBeTrue()
            ->and($reflection->hasMethod('validate'))->toBeTrue()
            ->and($reflection->hasMethod('regenerate'))->toBeTrue();

        $get = $reflection->getMethod('get');
        $validate = $reflection->getMethod('validate');
        $regenerate = $reflection->getMethod('regenerate');

        expect($get->getReturnType()?->getName())->toBe('string')
            ->and($validate->getReturnType()?->getName())->toBe('bool')
            ->and($validate->getParameters())->toHaveCount(1)
            ->and($validate->getParameters()[0]->getType()?->getName())->toBe('string')
            ->and($regenerate->getReturnType()?->getName())->toBe('string');
    });
});

describe('CsrfTokenManager', function (): void {
    it('generates a token and stores it in session', function (): void {
        $session = new FakeSession();
        $encryptor = new FakeEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $token = $manager->get();

        $encryptor->assertEncrypted();

        expect($token)->toBeString()
            ->and($token)->not->toBeEmpty()
            ->and($token)->toBe($encryptor->encrypted[0]['encrypted'])
            ->and($session->all())->toHaveKey('_csrf_token')
            ->and($session->get('_csrf_token'))->toBe($token);
    });

    it('returns existing token from session on subsequent calls', function (): void {
        $session = new FakeSession();
        $encryptor = new FakeEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $token1 = $manager->get();
        $token2 = $manager->get();

        expect($token1)->toBe($token2);
    });

    it('validates correct token successfully', function (): void {
        $session = new FakeSession();
        $encryptor = new FakeEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $token = $manager->get();

        expect($manager->validate($token))->toBeTrue();
    });

    it('rejects invalid token', function (): void {
        $session = new FakeSession();
        $encryptor = new FakeEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $manager->get();

        expect($manager->validate('invalid-token'))->toBeFalse();
    });

    it('regenerates token replacing the previous one', function (): void {
        $session = new FakeSession();
        $encryptor = new FakeEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $original = $manager->get();
        $regenerated = $manager->regenerate();

        expect($regenerated)->not->toBe($original)
            ->and($session->get('_csrf_token'))->toBe($regenerated)
            ->and($manager->get())->toBe($regenerated);
    });

    it('keeps accepting the token the request arrived with after a regeneration', function (): void {
        $session = new FakeSession();
        $session->set('_csrf_token', 'token-from-the-page');
        $manager = new CsrfTokenManager(session: $session, encryptor: new FakeEncryptor());

        $manager->regenerate();

        expect($manager->validate('token-from-the-page'))->toBeTrue();
    });

    it('accepts no token that was issued during the request and then replaced', function (): void {
        $session = new FakeSession();
        $session->set('_csrf_token', 'token-from-the-page');
        $manager = new CsrfTokenManager(session: $session, encryptor: new FakeEncryptor());

        $issuedDuringTheRequest = $manager->regenerate();
        $manager->regenerate();

        expect($manager->validate($issuedDuringTheRequest))->toBeFalse();
    });

    it('accepts no replaced token when the request arrived without one', function (): void {
        $manager = new CsrfTokenManager(session: new FakeSession(), encryptor: new FakeEncryptor());

        $issuedDuringTheRequest = $manager->get();
        $manager->regenerate();

        expect($manager->validate($issuedDuringTheRequest))->toBeFalse();
    });

    it('rejects the token the request arrived with once the manager is reset for the next request', function (): void {
        $session = new FakeSession();
        $session->set('_csrf_token', 'token-from-the-page');
        $manager = new CsrfTokenManager(session: $session, encryptor: new FakeEncryptor());

        $manager->regenerate();
        $manager->reset();

        expect($manager)->toBeInstanceOf(ResettableInterface::class)
            ->and($manager->validate('token-from-the-page'))->toBeFalse();
    });

    it('remembers the token the next request arrived with after a reset', function (): void {
        $session = new FakeSession();
        $session->set('_csrf_token', 'token-from-the-first-page');
        $manager = new CsrfTokenManager(session: $session, encryptor: new FakeEncryptor());
        $manager->regenerate();
        $manager->reset();

        $session->set('_csrf_token', 'token-from-the-second-page');
        $manager->regenerate();

        expect($manager->validate('token-from-the-second-page'))->toBeTrue();
    });

    it('implements CsrfTokenManagerInterface', function (): void {
        $session = new FakeSession();
        $encryptor = new FakeEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        expect($manager)->toBeInstanceOf(CsrfTokenManagerInterface::class);
    });

    it('uses FakeSession instead of inline session stub in CsrfTokenManagerTest', function (): void {
        $session = new FakeSession();
        $encryptor = new FakeEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $token = $manager->get();

        expect($session)->toBeInstanceOf(FakeSession::class)
            ->and($session->get('_csrf_token'))->toBe($token);
    });
});

describe('CsrfTokenMismatchException', function (): void {
    it('creates CsrfTokenMismatchException with three-part error pattern', function (): void {
        $exception = CsrfTokenMismatchException::invalidToken();

        expect($exception)->toBeInstanceOf(SecurityException::class)
            ->and($exception)->toBeInstanceOf(CsrfTokenMismatchException::class)
            ->and($exception->getMessage())->not->toBeEmpty()
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });
});
