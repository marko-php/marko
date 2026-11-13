---
title: marko/authentication-passkey
description: Passkey (WebAuthn) sign-in and passkey management --- passwordless login, password confirmation by passkey, and list, rename and delete for a user's passkeys.
---

Passkey (WebAuthn) sign-in and passkey management --- passwordless login, password confirmation by passkey, and list, rename and delete for a user's passkeys. The package adds passkeys to a session guard from [marko/authentication](/docs/packages/authentication/): `PasskeyRegistration` adds a passkey to the signed-in user's account, `PasskeyAuthentication` signs users in with one (including discoverable, username-less sign-in), and `PasskeyManager` lists, renames and deletes them. Passkeys are stored with [marko/database](/docs/packages/database/), failed sign-ins are throttled with [marko/ratelimiter](/docs/packages/ratelimiter/), and a small dependency-free JavaScript helper makes the browser calls.

## Installation

```bash
composer require marko/authentication-passkey
```

Then run `marko db:migrate` to create the `passkey_credentials` table.

## Configuration

```php title="config/authentication-passkey.php"
use Marko\Config\Env;

return [
    'relying_party' => [
        'id' => Env::nullableString('PASSKEY_RP_ID'),     // e.g. example.com
        'name' => Env::nullableString('PASSKEY_RP_NAME'), // e.g. Acme
    ],
    'allowed_origins' => Env::list('PASSKEY_ALLOWED_ORIGINS', []), // e.g. https://example.com
    'guard' => 'session',
    'timeout_seconds' => 300,
    'throttle' => [
        'max_attempts' => 5,
        'decay_seconds' => 60,
    ],
];
```

```bash title=".env"
PASSKEY_RP_ID=example.com
PASSKEY_RP_NAME=Acme
PASSKEY_ALLOWED_ORIGINS=https://example.com,https://app.example.com
```

| Key | Description |
|-----|-------------|
| `relying_party.id` | Your site's domain, without scheme or port. Passkeys created for it work on that domain and its subdomains only, so changing it later orphans every registered passkey. |
| `relying_party.name` | The name the browser shows while the user creates a passkey. |
| `allowed_origins` | The exact origins (scheme, host, port) your passkey pages are served from. Each host must be the relying party id or a subdomain of it, and each must use `https`; plain `http` is accepted for `localhost` alone. A default port is dropped, so `https://example.com:443` is `https://example.com`. |
| `guard` | The guard passkeys sign users in with. It must be a session guard; a stateless guard (such as the token guard) throws a `PasskeyConfigurationException`. Passkeys are stored with the name of this guard's user provider. |
| `timeout_seconds` | How long the browser waits for the user and a challenge stays valid. |
| `throttle` | A client (keyed by IP, or by marko/ratelimiter's `RateLimitKeyResolverInterface`) may make `max_attempts` sign-in or confirmation attempts within `decay_seconds`; a successful one resets the count. |

Nothing has a fallback in code: a missing relying party id or name, an empty origin list, an origin that is not `https` outside localhost, or an origin on another domain throws a `PasskeyConfigurationException` that names the key and how to fix it. For local development use `PASSKEY_RP_ID=localhost` and `PASSKEY_ALLOWED_ORIGINS=http://localhost:8000`.

## Usage

Every ceremony has two requests: the server issues options (with a fresh random challenge kept in the session), the browser answers them through the authenticator, and the server verifies the answer. Each challenge works once and expires after `timeout_seconds`, so a replayed response is rejected. Passkeys are always created as discoverable credentials and always require user verification (a PIN or biometric).

### The Browser Helper

The package ships `resources/js/passkeys.js`, a dependency-free ES module. Copy it into your frontend (or import it from `vendor/marko/authentication-passkey/resources/js/passkeys.js` with your bundler):

```javascript
import { createPasskey, getPasskey, passkeysSupported } from './passkeys.js';

const postJson = async (url, body = {}) => (await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
})).json();

// Add a passkey (signed in)
const creationOptions = await postJson('/passkeys/options');
await postJson('/passkeys', { name: 'MacBook Touch ID', credential: await createPasskey(creationOptions) });

// Sign in (signed out)
const requestOptions = await postJson('/login/passkey/options');
await postJson('/login/passkey', { credential: await getPasskey(requestOptions) });
```

`createPasskey()` and `getPasskey()` take the options JSON as returned by the server and resolve to the credential JSON (`PublicKeyCredential.toJSON()`) the server methods accept. `getPasskey(options, { conditional: true })` offers passkeys in the browser's autofill; the username input needs `autocomplete="username webauthn"`.

### Registering a Passkey

```php title="app/web/src/Controller/PasskeyController.php"
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\AuthenticationPasskey\Service\PasskeyRegistration;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AuthMiddleware::class)]
class PasskeyController
{
    public function __construct(
        private readonly PasskeyRegistration $passkeyRegistration,
    ) {}

    #[Post('/passkeys/options')]
    public function options(): Response
    {
        $user = $this->currentUser();

        return Response::json($this->passkeyRegistration->creationOptions(
            userName: $user->email,
            displayName: $user->name,
        ));
    }

    #[Post('/passkeys')]
    public function store(
        Request $request,
    ): Response {
        $passkey = $this->passkeyRegistration->register(
            credential: $request->json('credential'),
            name: (string) $request->json('name'),
        );

        return Response::json(['id' => $passkey->credentialId, 'name' => $passkey->name], 201);
    }
}
```

`creationOptions()` lists the user's existing passkeys in `excludeCredentials`, so the same authenticator is not registered twice. The WebAuthn user handle is the SHA-256 of the provider name and user id: stable across passkeys, and free of personal data. `register()` checks the challenge was issued to the same signed-in user, the origin, the relying party id and the attestation, rejects a credential id that is already registered, then stores the passkey and dispatches `PasskeyRegisteredEvent`.

### Signing In

```php title="app/web/src/Controller/PasskeyLoginController.php"
use Marko\Authentication\Middleware\GuestMiddleware;
use Marko\AuthenticationPasskey\Service\PasskeyAuthentication;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(GuestMiddleware::class)]
class PasskeyLoginController
{
    public function __construct(
        private readonly PasskeyAuthentication $passkeyAuthentication,
    ) {}

    #[Post('/login/passkey/options')]
    public function options(): Response
    {
        return Response::json($this->passkeyAuthentication->requestOptions());
    }

    #[Post('/login/passkey')]
    public function login(
        Request $request,
    ): Response {
        $this->passkeyAuthentication->authenticate($request->json('credential'));

        return Response::json(['redirect' => '/dashboard']);
    }
}
```

`requestOptions()` without a user asks for a discoverable credential: the browser offers every passkey it holds for the site, so no username is needed. Pass a user (`requestOptions($user)`) for a username-first form; only their passkeys are allowed then.

`authenticate()` verifies the assertion and then:

- looks the passkey up by credential id, for the configured guard's user provider, and checks the response's user handle (when sent) belongs to its owner
- checks the signature counter increased; authenticators that keep no counter always report `0`, which is accepted (WebAuthn §6.1.1), but a counter that goes backwards is rejected as a possible cloned authenticator
- stores the new counter and `lastUsedAt`, then logs the owner in with `GuardInterface::login()` (which fires `LoginEvent`)
- marks the session as password-confirmed (see below)

A rejected sign-in dispatches `PasskeyLoginFailedEvent` and throws `PasskeyAuthenticationFailedException`, rendered as a 401 with the same message whatever went wrong. After `max_attempts` attempts from one client within `decay_seconds`, further attempts throw `TooManyPasskeyAttemptsException` (429 with `Retry-After`) without being verified.

### Two-Factor Authentication

A passkey sign-in is already multi-factor: possession of the authenticator plus user verification by PIN or biometric, which is always required. `authenticate()` therefore logs in directly and never triggers the guard's [login challenges](/docs/packages/authentication/#login-challenges) (`authentication.guards.<name>.challenges`, such as [marko/authentication-two-factor](/docs/packages/authentication-two-factor/)'s code prompt). If a user started a password login, reached the two-factor prompt and then chose a passkey instead, the pending challenge is cancelled. Password logins through `attempt()` still go through every challenge.

### Password Confirmation

A successful passkey assertion counts as proof of presence for `RequirePasswordConfirmationMiddleware` from [marko/authentication](/docs/packages/authentication/#password-confirmation): `authenticate()` calls `PasswordConfirmationInterface::markConfirmed()` right after the login, so the user can reach confirmed routes at once. Once that confirmation is older than `authentication.password_confirmation.timeout_seconds`, a user (including one with no password at all) can confirm again with a passkey without logging in again:

```php
// Options limited to the signed-in user's passkeys
$options = $passkeyAuthentication->requestOptions($currentUser);

// Verify the response; the session is password-confirmed afterwards
$passkeyAuthentication->confirm($credentialJson);
```

`confirm()` only accepts the signed-in user's own passkeys and is throttled like `authenticate()`.

### Managing Passkeys

```php title="app/web/src/Controller/PasskeySettingsController.php"
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Authentication\Middleware\RequirePasswordConfirmationMiddleware;
use Marko\AuthenticationPasskey\Entity\PasskeyCredential;
use Marko\AuthenticationPasskey\Service\PasskeyManager;
use Marko\Routing\Attributes\Delete;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Patch;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

#[Middleware(AuthMiddleware::class)]
class PasskeySettingsController
{
    public function __construct(
        private readonly PasskeyManager $passkeyManager,
    ) {}

    #[Get('/passkeys')]
    public function index(): Response
    {
        return Response::json(array_map(fn (PasskeyCredential $passkey): array => [
            'id' => $passkey->credentialId,
            'name' => $passkey->name,
            'created_at' => $passkey->createdAt->format(DATE_ATOM),
            'last_used_at' => $passkey->lastUsedAt?->format(DATE_ATOM),
        ], $this->passkeyManager->forCurrentUser()));
    }

    #[Patch('/passkeys/{credentialId}')]
    public function rename(
        string $credentialId,
        Request $request,
    ): Response {
        $this->passkeyManager->rename($credentialId, (string) $request->json('name'));

        return Response::json(['renamed' => true]);
    }

    #[Delete('/passkeys/{credentialId}', middleware: [RequirePasswordConfirmationMiddleware::class])]
    public function destroy(
        string $credentialId,
    ): Response {
        $this->passkeyManager->delete($credentialId);

        return Response::json(['deleted' => true]);
    }
}
```

Every method acts on the signed-in user's own passkeys; another user's credential id throws `PasskeyNotFoundException` (404), so ids cannot be probed. Deleting needs a recent password confirmation: put `RequirePasswordConfirmationMiddleware` on the route, and `delete()` enforces it as well by throwing `PasswordConfirmationRequiredException` (423). Names are trimmed and must be 1 to 255 characters (`InvalidPasskeyNameException`, 422).

## Events

| Event | Dispatched when | Properties |
|-------|-----------------|------------|
| `PasskeyRegisteredEvent` | `register()` stored a new passkey | `user`, `credential`, `guard` |
| `PasskeyDeletedEvent` | `delete()` removed a passkey | `user`, `credential`, `guard` |
| `PasskeyLoginFailedEvent` | `authenticate()` or `confirm()` rejected an assertion | `guard`, `reason` (`PasskeyFailureReason`), `credentialId`, `ipAddress` |

`PasskeyLoginFailedEvent` carries no secret (a credential id is a public identifier), so it is safe to log:

```php title="app/security/src/Observer/LogFailedPasskeyLogin.php"
use Marko\AuthenticationPasskey\Event\PasskeyLoginFailedEvent;
use Marko\Core\Attributes\Observer;
use Psr\Log\LoggerInterface;

#[Observer(PasskeyLoginFailedEvent::class)]
readonly class LogFailedPasskeyLogin
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function handle(
        PasskeyLoginFailedEvent $event,
    ): void {
        $this->logger->warning('Passkey sign-in failed', [
            'reason' => $event->reason->value,
            'credential_id' => $event->credentialId,
            'ip' => $event->ipAddress,
        ]);
    }
}
```

`PasskeyFailureReason` cases: `MalformedResponse`, `ChallengeMismatch` (no pending challenge, expired, already used, or a different one), `OriginMismatch`, `RelyingPartyMismatch`, `UserNotVerified`, `InvalidSignature`, `UnknownCredential`, `UserHandleMismatch`, `SignCountRegressed`, `UnknownUser`, `CredentialAlreadyRegistered`.

## Database

`PasskeyCredential` is a [marko/database](/docs/packages/database/) entity on the `passkey_credentials` table, which `marko db:migrate` creates:

| Column | Description |
|--------|-------------|
| `credential_id` | The credential id, base64url (unique, up to 512 characters) |
| `provider`, `user_id` | The owner: the `authentication.providers` name and the user id |
| `name` | The user's label for the passkey |
| `public_key` | The credential public key, PEM |
| `sign_count` | The authenticator's last signature counter |
| `transports` | JSON list of transport hints, e.g. `["internal","hybrid"]` |
| `created_at`, `last_used_at` | When it was registered and last used |

To store passkeys elsewhere, bind your own `PasskeyCredentialRepositoryInterface` in your module's `module.php`.

## WebAuthn Engine

`PasskeyVerifierInterface` is the only code that touches a WebAuthn library. The default binding, `LbuchsWebAuthnVerifier`, uses [lbuchs/webauthn](https://github.com/lbuchs/WebAuthn), which has no dependencies of its own and verifies the `none`, `packed`, `fido-u2f`, `tpm`, `apple`, `android-key` and `android-safetynet` attestation formats with ES256, EdDSA and RS256 keys. Passkeys are requested with attestation `none`, so any authenticator model is accepted.

Challenges, the client data checks (type, challenge, exact origin), the signature counter, the user handle and throttling all live in the package, outside the engine. To use another library, implement the interface's four methods and bind it:

```php title="module.php"
use App\Passkeys\WebAuthnLibVerifier;
use Marko\AuthenticationPasskey\Contracts\PasskeyVerifierInterface;

return [
    'bindings' => [
        PasskeyVerifierInterface::class => WebAuthnLibVerifier::class,
    ],
];
```

## API Reference

### PasskeyRegistration

```php
public function creationOptions(string $userName, string $displayName): array;
public function register(array $credential, string $name): PasskeyCredential;
```

### PasskeyAuthentication

```php
public function requestOptions(?AuthenticatableInterface $user = null): array;
public function authenticate(array $credential): AuthenticatableInterface;
public function confirm(array $credential): void;
```

### PasskeyManager

```php
/** @return list<PasskeyCredential> */
public function forCurrentUser(): array;
public function rename(string $credentialId, string $name): PasskeyCredential;
public function delete(string $credentialId): void;
```

### PasskeyCredentialRepositoryInterface

```php
public function findByCredentialId(string $id): ?PasskeyCredential;
/** @return list<PasskeyCredential> */
public function forUser(string $provider, int|string $userId): array;
public function save(PasskeyCredential $credential): void;
public function delete(PasskeyCredential $credential): void;
```

### PasskeyVerifierInterface

```php
public function creationOptions(PasskeyUserEntity $user, string $challenge, array $excludeCredentials): array;
public function verifyAttestation(AttestationResponse $response, string $challenge): VerifiedAttestation;
public function requestOptions(string $challenge, array $allowCredentials): array;
public function verifyAssertion(AssertionResponse $response, PasskeyCredential $credential, string $challenge): VerifiedAssertion;
```

### Exceptions

| Exception | Status | Thrown when |
|-----------|--------|-------------|
| `PasskeyConfigurationException` | 500 | A required setting is missing or invalid, the guard is stateless, or the guard names no user provider |
| `PasskeyVerificationException` | 400 | A registration response is rejected; `getReason()` says why |
| `PasskeyAuthenticationFailedException` | 401 | A sign-in or confirmation is rejected; `getReason()` says why, the response does not |
| `TooManyPasskeyAttemptsException` | 429 | The client is locked out; carries `Retry-After` |
| `PasskeyNotFoundException` | 404 | The signed-in user has no passkey with that credential id |
| `InvalidPasskeyNameException` | 422 | A passkey name is empty or longer than 255 characters |

## Related Packages

- [marko/authentication](/docs/packages/authentication/) --- guards, password confirmation and login challenges
- [marko/authentication-two-factor](/docs/packages/authentication-two-factor/) --- TOTP codes for password logins
- [marko/ratelimiter](/docs/packages/ratelimiter/) --- the rate limiter behind passkey throttling
- [marko/database](/docs/packages/database/) --- stores passkeys
