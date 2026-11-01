<?php

declare(strict_types=1);

use Marko\AuthenticationTwoFactor\Contracts\TotpInterface;
use Marko\AuthenticationTwoFactor\Totp\OtphpTotp;
use Marko\Testing\Fake\FakeClock;
use OTPHP\TOTP;

/** Base32 of the RFC 6238 Appendix B SHA-1 seed "12345678901234567890". */
const RFC_6238_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

function totpAt(
    int $timestamp,
): OtphpTotp {
    return new OtphpTotp(new FakeClock(new DateTimeImmutable("@$timestamp")));
}

it('implements TotpInterface', function (): void {
    expect(totpAt(0))->toBeInstanceOf(TotpInterface::class);
});

it('accepts the RFC 6238 SHA-1 test vectors at their time step', function (int $timestamp, string $code): void {
    expect(totpAt($timestamp)->verify(RFC_6238_SECRET, $code, null))->toBe(intdiv($timestamp, 30));
})->with([
    // Appendix B lists 8-digit codes; a 6-digit code is the same value's last six digits
    'T=59' => [59, '287082'],
    'T=1111111109' => [1111111109, '081804'],
    'T=1111111111' => [1111111111, '050471'],
    'T=1234567890' => [1234567890, '005924'],
    'T=2000000000' => [2000000000, '279037'],
    'T=20000000000' => [20000000000, '353130'],
]);

it('accepts a code from one step before or after the current step', function (): void {
    // 287082 is the code for step 1 (T=30..59)
    expect(totpAt(89)->verify(RFC_6238_SECRET, '287082', null))->toBe(1)
        ->and(totpAt(5)->verify(RFC_6238_SECRET, '287082', null))->toBe(1);
});

it('rejects a code outside the one-step window', function (): void {
    expect(totpAt(90)->verify(RFC_6238_SECRET, '287082', null))->toBeNull();
});

it('rejects a code for the last used step or an earlier one', function (): void {
    $totp = totpAt(59);

    expect($totp->verify(RFC_6238_SECRET, '287082', 1))->toBeNull()
        ->and($totp->verify(RFC_6238_SECRET, '287082', 2))->toBeNull()
        ->and($totp->verify(RFC_6238_SECRET, '287082', 0))->toBe(1);
});

it('rejects a wrong or malformed code', function (string $code): void {
    expect(totpAt(59)->verify(RFC_6238_SECRET, $code, null))->toBeNull();
})->with(['000000', '28708', '2870822', 'abcdef', '']);

it('ignores whitespace inside the code', function (): void {
    expect(totpAt(59)->verify(RFC_6238_SECRET, '287 082', null))->toBe(1);
});

it('generates a 160-bit base32 secret that verifies its own codes', function (): void {
    $totp = totpAt(1_700_000_000);
    $secret = $totp->generateSecret();

    expect($secret)->toMatch('/^[A-Z2-7]{32}$/')
        ->and($totp->generateSecret())->not->toBe($secret)
        ->and($totp->verify($secret, TOTP::createFromSecret($secret, new FakeClock())->at(1_700_000_000), null))
        ->toBe(intdiv(1_700_000_000, 30));
});

it('builds an otpauth provisioning URI with the label and issuer', function (): void {
    $uri = totpAt(0)->provisioningUri(RFC_6238_SECRET, 'ada@example.com', 'Acme');

    expect($uri)->toStartWith('otpauth://totp/Acme%3Aada%40example.com?')
        ->and($uri)->toContain('secret=' . RFC_6238_SECRET)
        ->and($uri)->toContain('issuer=Acme');
});
