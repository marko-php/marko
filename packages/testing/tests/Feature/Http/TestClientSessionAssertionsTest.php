<?php

declare(strict_types=1);

use Marko\Authentication\AuthManager;
use Marko\Authentication\Contracts\StatelessGuardInterface;
use Marko\Session\Contracts\SessionInterface;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Exceptions\TestClientException;
use Marko\Testing\Fake\FakeAuthenticatable;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Http\TestClient;
use Marko\Testing\Tests\HttpApp\RequestCounter;

use function Marko\Testing\Tests\httpAppPath;

use function Marko\Testing\Tests\removeHttpAppSessions;

use PHPUnit\Framework\AssertionFailedError;

afterAll(function (): void {
    removeHttpAppSessions();
});

/**
 * Every session file the fixture app holds for this process, path => contents.
 *
 * @return array<string, string>
 */
function sessionProbeFiles(): array
{
    $files = [];

    foreach (glob(sys_get_temp_dir() . '/marko-testing-http-app/' . getmypid() . '/sessions/*') ?: [] as $path) {
        $files[$path] = (string) file_get_contents($path);
    }

    return $files;
}

describe('TestResponse session assertions', function (): void {
    it('passes assertSessionHas for a key the request stored', function (): void {
        TestClient::boot(httpAppPath())
            ->get('/session/put', ['plan' => 'pro'])
            ->assertSessionHas('plan')
            ->assertSessionHas('plan', 'pro')
            ->assertSessionHas('plan', fn (mixed $value): bool => $value === 'pro');
    });

    it('fails assertSessionHas for a missing key', function (): void {
        TestClient::boot(httpAppPath())
            ->get('/session/put', ['plan' => 'pro'])
            ->assertSessionHas('team');
    })->throws(AssertionFailedException::class, 'Expected the session to have [team] but it does not.');

    it('fails assertSessionHas for a different value', function (): void {
        TestClient::boot(httpAppPath())
            ->get('/session/put', ['plan' => 'pro'])
            ->assertSessionHas('plan', 'free');
    })->throws(AssertionFailedException::class, "Expected session [plan] to be 'free' but it was 'pro'.");

    it('fails assertSessionHas when the callback returns false', function (): void {
        TestClient::boot(httpAppPath())
            ->get('/session/put', ['plan' => 'pro'])
            ->assertSessionHas('plan', fn (mixed $value): bool => $value === 'free');
    })->throws(AssertionFailedException::class, "Expected session [plan] to pass the callback but it was 'pro'.");

    it('compares a callable string as a value, not as a callback', function (): void {
        TestClient::boot(httpAppPath())
            ->get('/session/put', ['format' => 'date'])
            ->assertSessionHas('format', 'date');
    });

    it('passes assertSessionMissing for an absent key and fails for a stored one', function (): void {
        $response = TestClient::boot(httpAppPath())->get('/session/put', ['plan' => 'pro']);

        $response->assertSessionMissing('team');

        expect(fn () => $response->assertSessionMissing('plan'))
            ->toThrow(AssertionFailedException::class, "Expected the session not to have [plan] but it was 'pro'.");
    });

    it('asserts on a flash message without consuming it', function (): void {
        $client = TestClient::boot(httpAppPath());

        $client->get('/session/flash', ['type' => 'success', 'message' => 'Saved.'])
            ->assertSessionHasFlash('success')
            ->assertSessionHasFlash('success', 'Saved.')
            ->assertSessionHasFlash('success', 'Saved.');

        $client->get('/session/flash/read', ['type' => 'success'])->assertSee('Saved.');
    });

    it('fails assertSessionHasFlash for a missing type or message', function (): void {
        $response = TestClient::boot(httpAppPath())
            ->get('/session/flash', ['type' => 'success', 'message' => 'Saved.']);

        expect(fn () => $response->assertSessionHasFlash('error'))
            ->toThrow(AssertionFailedException::class, 'Expected a [error] flash message but there was none.')
            ->and(fn () => $response->assertSessionHasFlash('success', 'Deleted.'))
            ->toThrow(
                AssertionFailedException::class,
                'Expected a [success] flash message [Deleted.] but the [success] messages were ["Saved."].',
            );
    });
});

describe('SessionProbe', function (): void {
    it('sees an empty session when there is no session cookie', function (): void {
        TestClient::boot(httpAppPath())
            ->get('/counter')
            ->assertSessionMissing('plan');
    });

    it('sees an empty session after the response expired the session cookie', function (): void {
        $client = TestClient::boot(httpAppPath());
        $client->get('/session/put', ['plan' => 'pro'])->assertSessionHas('plan');

        $client->get('/session/destroy')->assertSessionMissing('plan');

        expect($client->cookies())->not->toHaveKey('marko_session');
    });

    it('never writes the session', function (): void {
        $client = TestClient::boot(httpAppPath());
        $response = $client->get('/session/flash', ['type' => 'success', 'message' => 'Saved.']);
        $before = sessionProbeFiles();

        $response->assertSessionHasFlash('success')->assertSessionHas('_flash');
        $client->assertGuest();

        expect(sessionProbeFiles())->toBe($before);
    });

    it('creates no session when the client has no session cookie', function (): void {
        $client = TestClient::boot(httpAppPath());
        $response = $client->get('/counter');
        $before = sessionProbeFiles();

        $response->assertSessionMissing('plan');
        $client->assertGuest();

        expect(sessionProbeFiles())->toBe($before);
    });

    it('resets request-scoped state before reading, like a request', function (): void {
        $client = TestClient::boot(httpAppPath());
        $counter = $client->application()->container->get(RequestCounter::class);

        $client->get('/counter')->assertSessionMissing('plan');

        expect($counter->increment())->toBe(1);
    });

    it('leaves services passed to withoutResetting alone', function (): void {
        $client = TestClient::boot(httpAppPath());
        $counter = $client->application()->container->get(RequestCounter::class);
        $client->withoutResetting($counter);

        $client->get('/counter')->assertSessionMissing('plan');

        expect($counter->increment())->toBe(2);
    });

    it('leaves the session closed afterwards', function (): void {
        $client = TestClient::boot(httpAppPath());
        $client->get('/session/put', ['plan' => 'pro'])->assertSessionHas('plan');

        expect($client->application()->container->get(SessionInterface::class)->started)->toBeFalse();
    });

    it('throws for a response that is no longer the latest', function (): void {
        $client = TestClient::boot(httpAppPath());
        $first = $client->get('/session/put', ['plan' => 'pro']);
        $client->get('/session/put', ['plan' => 'free']);

        $first->assertSessionHas('plan');
    })->throws(TestClientException::class, 'this response is stale: a later request has run on the client');
});

describe('TestClient auth assertions', function (): void {
    it('asserts a guest before any login', function (): void {
        $client = TestClient::boot(httpAppPath());
        $client->get('/counter');

        $client->assertGuest();

        expect(fn () => $client->assertAuthenticated())
            ->toThrow(
                AssertionFailedException::class,
                'Expected a user to be authenticated on guard [web] but there is none.',
            );
    });

    it('asserts the user the real session guard logged in', function (): void {
        $client = TestClient::boot(httpAppPath());
        $client->post('/login', ['user_id' => '1'])->assertRedirect('/dashboard');

        $client->assertAuthenticated()
            ->assertAuthenticated('web')
            ->assertAuthenticatedAs(new FakeAuthenticatable(id: 1));

        expect(fn () => $client->assertGuest())
            ->toThrow(
                AssertionFailedException::class,
                'Expected a guest on guard [web] but user [1] is authenticated.',
            )
            ->and(fn () => $client->assertAuthenticatedAs(new FakeAuthenticatable(id: 2)))
            ->toThrow(
                AssertionFailedException::class,
                'Expected user [2] to be authenticated on guard [web] but user [1] is.',
            );
    });

    it('reads the guard from the session, not from the guard cache', function (): void {
        $client = TestClient::boot(httpAppPath());
        $client->post('/login', ['user_id' => '1']);
        $client->withoutCookies();

        $client->assertGuest();
    });

    it('asserts a guest after logout', function (): void {
        $client = TestClient::boot(httpAppPath());
        $client->post('/login', ['user_id' => '1']);
        $client->post('/logout')->assertRedirect('/');

        $client->assertGuest();

        expect(fn () => $client->assertAuthenticatedAs(new FakeAuthenticatable(id: 1)))
            ->toThrow(
                AssertionFailedException::class,
                'Expected user [1] to be authenticated on guard [web] but there is none.',
            );
    });

    it('reports the FakeGuard state after actingAs', function (): void {
        $client = TestClient::boot(httpAppPath())->actingAs(new FakeAuthenticatable(id: 7));

        $client->assertAuthenticatedAs(new FakeAuthenticatable(id: 7));
    });

    it('throws for a stateless guard', function (): void {
        $client = TestClient::boot(httpAppPath());
        $client->application()->container->get(AuthManager::class)->useGuard(
            'api',
            new class (name: 'api') extends FakeGuard implements StatelessGuardInterface
            {
                public function getChallenge(): string
                {
                    return 'Bearer';
                }
            },
        );

        $client->assertGuest('api');
    })->throws(TestClientException::class, 'Guard [api] is stateless');
});

describe('TestResponse JSON validation assertions', function (): void {
    it('asserts the errors of a real ValidationException 422 response', function (): void {
        TestClient::boot(httpAppPath())
            ->post('/profile', ['email' => 'not-an-email'], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email'])
            ->assertJsonValidationErrors('email')
            ->assertJsonValidationErrors(['email' => 'The email field must be a valid email address.'])
            ->assertJsonMissingValidationErrors('phone');
    });

    it('fails for a field without an error', function (): void {
        TestClient::boot(httpAppPath())
            ->post('/profile', ['name' => 'Ada', 'email' => 'nope'], ['Accept' => 'application/json'])
            ->assertJsonValidationErrors('name');
    })->throws(AssertionFailedException::class, 'Expected a JSON validation error for [name] but there was none.');

    it('asserts no validation errors on a valid request', function (): void {
        TestClient::boot(httpAppPath())
            ->post('/profile', ['name' => 'Ada', 'email' => 'ada@example.com'], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonMissingValidationErrors();
    });
});

describe('Pest expectations for sessions and auth', function (): void {
    it('passes toHaveSession for a stored key and value', function (): void {
        expect(TestClient::boot(httpAppPath())->get('/session/put', ['plan' => 'pro']))
            ->toHaveSession('plan')
            ->toHaveSession('plan', 'pro');
    });

    it('fails toHaveSession with the TestResponse message', function (): void {
        expect(fn () => expect(TestClient::boot(httpAppPath())->get('/counter'))->toHaveSession('plan'))
            ->toThrow(AssertionFailedError::class, 'Expected the session to have [plan] but it does not.');
    });

    it('passes toBeAuthenticated for a TestClient after a real login', function (): void {
        $client = TestClient::boot(httpAppPath());
        $client->post('/login', ['user_id' => '1']);

        expect($client)->toBeAuthenticated()->toBeAuthenticated('web');
    });

    it('fails toBeAuthenticated for a guest TestClient', function (): void {
        expect(fn () => expect(TestClient::boot(httpAppPath()))->toBeAuthenticated())
            ->toThrow(
                AssertionFailedError::class,
                'Expected a user to be authenticated on guard [web] but there is none.',
            );
    });
});

describe('TestResponse assertDownload', function (): void {
    it('passes for a FileResponse download', function (): void {
        TestClient::boot(httpAppPath())
            ->get('/download')
            ->assertDownload()
            ->assertDownload('fixture.json');
    });
});
