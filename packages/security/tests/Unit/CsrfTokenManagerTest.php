<?php

declare(strict_types=1);

use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\CsrfTokenManager;
use Marko\Security\Exceptions\CsrfTokenMismatchException;
use Marko\Security\Exceptions\SecurityException;
use Marko\Session\Contracts\SessionInterface;
use Marko\Session\Flash\FlashBag;

function createStubSession(
    array &$store = [],
): SessionInterface {
    return new class ($store) implements SessionInterface
    {
        public bool $started {
            get => true;
        }

        public function __construct(
            private array &$store,
        ) {}

        public function start(): void {}

        public function get(
            string $key,
            mixed $default = null,
        ): mixed {
            return $this->store[$key] ?? $default;
        }

        public function set(
            string $key,
            mixed $value,
        ): void {
            $this->store[$key] = $value;
        }

        public function has(
            string $key,
        ): bool {
            return isset($this->store[$key]);
        }

        public function remove(
            string $key,
        ): void {
            unset($this->store[$key]);
        }

        public function clear(): void
        {
            $this->store = [];
        }

        public function all(): array
        {
            return $this->store;
        }

        public function regenerate(bool $deleteOldSession = true): void {}

        public function destroy(): void {}

        public function getId(): string
        {
            return 'test-session-id';
        }

        public function setId(string $id): void {}

        public function flash(): FlashBag
        {
            return new FlashBag();
        }

        public function save(): void {}
    };
}

function createStubEncryptor(): EncryptorInterface
{
    return new class () implements EncryptorInterface
    {
        private int $counter = 0;

        public function encrypt(
            string $value,
        ): string {
            $this->counter++;

            return 'encrypted_' . $this->counter . '_' . bin2hex($value);
        }

        public function decrypt(
            string $encrypted,
        ): string {
            return 'decrypted_' . $encrypted;
        }
    };
}

describe('CsrfTokenManagerInterface', function (): void {
    it('defines CsrfTokenManagerInterface with get validate and regenerate methods', function (): void {
        $reflection = new ReflectionClass(CsrfTokenManagerInterface::class);

        expect($reflection->isInterface())->toBeTrue()
            ->and($reflection->hasMethod('get'))->toBeTrue()
            ->and($reflection->hasMethod('validate'))->toBeTrue()
            ->and($reflection->hasMethod('regenerate'))->toBeTrue();

        $get = $reflection->getMethod('get');
        expect($get->getReturnType()?->getName())->toBe('string');

        $validate = $reflection->getMethod('validate');
        expect($validate->getReturnType()?->getName())->toBe('bool');
        expect($validate->getParameters())->toHaveCount(1);
        expect($validate->getParameters()[0]->getType()?->getName())->toBe('string');

        $regenerate = $reflection->getMethod('regenerate');
        expect($regenerate->getReturnType()?->getName())->toBe('string');
    });
});

describe('CsrfTokenManager', function (): void {
    it('generates a token and stores it in session', function (): void {
        $store = [];
        $session = createStubSession($store);
        $encryptor = createStubEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $token = $manager->get();

        expect($token)->toBeString()
            ->and($token)->not->toBeEmpty()
            ->and($store)->toHaveKey('_csrf_token')
            ->and($store['_csrf_token'])->toBe($token);
    });

    it('returns existing token from session on subsequent calls', function (): void {
        $store = [];
        $session = createStubSession($store);
        $encryptor = createStubEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $token1 = $manager->get();
        $token2 = $manager->get();

        expect($token1)->toBe($token2);
    });

    it('validates correct token successfully', function (): void {
        $store = [];
        $session = createStubSession($store);
        $encryptor = createStubEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $token = $manager->get();

        expect($manager->validate($token))->toBeTrue();
    });

    it('rejects invalid token', function (): void {
        $store = [];
        $session = createStubSession($store);
        $encryptor = createStubEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $manager->get();

        expect($manager->validate('invalid-token'))->toBeFalse();
    });

    it('regenerates token replacing the previous one', function (): void {
        $store = [];
        $session = createStubSession($store);
        $encryptor = createStubEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        $original = $manager->get();
        $regenerated = $manager->regenerate();

        expect($regenerated)->not->toBe($original)
            ->and($store['_csrf_token'])->toBe($regenerated)
            ->and($manager->get())->toBe($regenerated);
    });

    it('implements CsrfTokenManagerInterface', function (): void {
        $store = [];
        $session = createStubSession($store);
        $encryptor = createStubEncryptor();

        $manager = new CsrfTokenManager(
            session: $session,
            encryptor: $encryptor,
        );

        expect($manager)->toBeInstanceOf(CsrfTokenManagerInterface::class);
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
