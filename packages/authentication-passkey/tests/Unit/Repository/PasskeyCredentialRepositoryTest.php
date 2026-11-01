<?php

declare(strict_types=1);

use Marko\AuthenticationPasskey\Contracts\PasskeyCredentialRepositoryInterface;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Repository\PasskeyCredentialRepository;
use Marko\AuthenticationPasskey\Tests\Fixtures\PasskeyHarness;

function passkeyEntity(
    string $credentialId,
    string $provider = 'users',
    int|string $userId = 7,
): PasskeyCredential {
    $credential = new PasskeyCredential();
    $credential->credentialId = $credentialId;
    $credential->provider = $provider;
    $credential->userId = (string) $userId;
    $credential->name = "Key $credentialId";
    $credential->publicKey = 'pem';
    $credential->signCount = 3;
    $credential->transports = ['usb', 'nfc'];
    $credential->createdAt = new DateTimeImmutable('2026-06-01 12:00:00');

    return $credential;
}

beforeEach(function (): void {
    $this->harness = new PasskeyHarness();
    $this->repository = $this->harness->repository;
});

it('is the module binding for the repository interface', function (): void {
    $module = require dirname(__DIR__, 3) . '/module.php';

    expect($module['bindings'][PasskeyCredentialRepositoryInterface::class])->toBe(PasskeyCredentialRepository::class)
        ->and($this->repository)->toBeInstanceOf(PasskeyCredentialRepositoryInterface::class);
});

it('saves a passkey and finds it again by credential id', function (): void {
    $this->repository->save(passkeyEntity('abc'));

    $found = $this->repository->findByCredentialId('abc');

    expect($found->id)->toBe(1)
        ->and($found->userId)->toBe('7')
        ->and($found->signCount)->toBe(3)
        ->and($found->transports)->toBe(['usb', 'nfc'])
        ->and($found->createdAt->format('Y-m-d H:i:s'))->toBe('2026-06-01 12:00:00')
        ->and($found->lastUsedAt)->toBeNull()
        ->and($this->repository->findByCredentialId('missing'))->toBeNull();
});

it('lists one user\'s passkeys of one provider, oldest first', function (): void {
    $this->repository->save(passkeyEntity('first'));
    $this->repository->save(passkeyEntity('other-user', userId: 8));
    $this->repository->save(passkeyEntity('other-provider', provider: 'admins'));
    $this->repository->save(passkeyEntity('second'));

    expect(
        array_map(fn (PasskeyCredential $passkey) => $passkey->credentialId, $this->repository->forUser('users', 7)),
    )
        ->toBe(['first', 'second'])
        ->and($this->repository->forUser('users', '8'))->toHaveCount(1);
});

it('updates and deletes a passkey', function (): void {
    $passkey = passkeyEntity('abc');
    $this->repository->save($passkey);
    $passkey->signCount = 9;
    $this->repository->save($passkey);

    expect($this->repository->findByCredentialId('abc')->signCount)->toBe(9);

    $this->repository->delete($passkey);

    expect($this->harness->connection->rows())->toBe([]);
});
