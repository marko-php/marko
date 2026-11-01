<?php

declare(strict_types=1);

use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Exceptions\TestClientException;
use Marko\Testing\Http\TestClient;
use Marko\Testing\Http\TestResponse;

use function Marko\Testing\Tests\httpAppPath;
use function Marko\Testing\Tests\removeHttpAppSessions;

use PHPUnit\Framework\AssertionFailedError;

afterAll(function (): void {
    removeHttpAppSessions();
});

/**
 * A failed contact form POST that marko/forms turns into a redirect back with
 * the errors and input flashed: name and address.city missing, email invalid.
 */
function failedContactSubmission(
    ?TestClient $client = null,
): TestResponse {
    return ($client ?? TestClient::boot(httpAppPath()))->post('/contact', [
        'name' => '',
        'email' => 'not-an-email',
        'password' => 'hunter22',
        'address' => ['street' => '1 Main St'],
    ]);
}

describe('assertSessionHasErrors', function (): void {
    it('passes for each field the redirect back flashed an error for', function (): void {
        failedContactSubmission()
            ->assertRedirect('/contact')
            ->assertSessionHasErrors('name')
            ->assertSessionHasErrors(['name', 'email', 'address.city'])
            ->assertSessionHasErrors();
    });

    it('passes for a field whose error contains the message substring', function (): void {
        failedContactSubmission()->assertSessionHasErrors([
            'email' => 'must be a valid email',
            'name',
        ]);
    });

    it('fails for a field with no flashed error', function (): void {
        failedContactSubmission()->assertSessionHasErrors(['name', 'phone']);
    })->throws(
        AssertionFailedException::class,
        'Expected a flashed validation error for [phone] but there was none. The fields with errors were ["name","email","address.city"].',
    );

    it('fails when no error of the field contains the message substring', function (): void {
        failedContactSubmission()->assertSessionHasErrors(['email' => 'is required']);
    })->throws(
        AssertionFailedException::class,
        'Expected a flashed validation error for [email] containing [is required] but its errors were ["The email field must be a valid email address."].',
    );

    it('fails with no fields when nothing was flashed', function (): void {
        TestClient::boot(httpAppPath())->get('/contact')->assertSessionHasErrors();
    })->throws(AssertionFailedException::class, 'Expected flashed validation errors but there were none.');
});

describe('assertSessionHasNoErrors and assertSessionDoesntHaveErrors', function (): void {
    it('passes assertSessionHasNoErrors after a valid submission', function (): void {
        TestClient::boot(httpAppPath())
            ->post('/contact', ['name' => 'Ada', 'email' => 'ada@example.com', 'address' => ['city' => 'London']])
            ->assertOk()
            ->assertSessionHasNoErrors();
    });

    it('fails assertSessionHasNoErrors after a failed submission', function (): void {
        failedContactSubmission()->assertSessionHasNoErrors();
    })->throws(
        AssertionFailedException::class,
        'Expected no flashed validation errors but there were: {"name":["The name field is required."]',
    );

    it('passes assertSessionDoesntHaveErrors for fields without errors', function (): void {
        failedContactSubmission()
            ->assertSessionDoesntHaveErrors('password')
            ->assertSessionDoesntHaveErrors(['password', 'address.street']);
    });

    it('fails assertSessionDoesntHaveErrors for a field with an error', function (): void {
        failedContactSubmission()->assertSessionDoesntHaveErrors(['password', 'email']);
    })->throws(
        AssertionFailedException::class,
        'Expected no flashed validation error for [email] but it has ["The email field must be a valid email address."].',
    );
});

describe('assertSessionHasInput and assertSessionMissingInput', function (): void {
    it('passes assertSessionHasInput for flashed input, with and without a value', function (): void {
        failedContactSubmission()
            ->assertSessionHasInput('email')
            ->assertSessionHasInput('email', 'not-an-email')
            ->assertSessionHasInput('name', '')
            ->assertSessionHasInput('address.street', '1 Main St')
            ->assertSessionHasInput('email', fn (mixed $value): bool => str_contains((string) $value, 'not-an'));
    });

    it('fails assertSessionHasInput for input that was not flashed', function (): void {
        failedContactSubmission()->assertSessionHasInput('phone');
    })->throws(AssertionFailedException::class, 'Expected the session to have flashed input [phone] but it does not.');

    it('fails assertSessionHasInput for a different value', function (): void {
        failedContactSubmission()->assertSessionHasInput('email', 'ada@example.com');
    })->throws(
        AssertionFailedException::class,
        "Expected flashed input [email] to be 'ada@example.com' but it was 'not-an-email'.",
    );

    it('fails assertSessionHasInput when the callback returns false', function (): void {
        failedContactSubmission()->assertSessionHasInput('email', fn (mixed $value): bool => false);
    })->throws(
        AssertionFailedException::class,
        "Expected flashed input [email] to pass the callback but it was 'not-an-email'.",
    );

    it('passes assertSessionMissingInput for a field marko/forms never flashes', function (): void {
        failedContactSubmission()
            ->assertSessionMissingInput('password')
            ->assertSessionMissingInput('phone');
    });

    it('fails assertSessionMissingInput for flashed input', function (): void {
        failedContactSubmission()->assertSessionMissingInput('email');
    })->throws(
        AssertionFailedException::class,
        "Expected the session not to have flashed input [email] but it was 'not-an-email'.",
    );
});

describe('flashed form state assertions and the flash lifetime', function (): void {
    it('does not use up the flash: the redirected-to page still sees the errors', function (): void {
        $client = TestClient::boot(httpAppPath());

        failedContactSubmission($client)
            ->assertSessionHasErrors('email')
            ->assertSessionHasInput('email', 'not-an-email')
            ->assertSessionHasErrors('email');

        $client->get('/contact')
            ->assertJsonPath('errors.email.0', 'The email field must be a valid email address.')
            ->assertJsonPath('email', 'not-an-email');
    });

    it('sees no errors on the request after the one that showed them', function (): void {
        $client = TestClient::boot(httpAppPath());
        failedContactSubmission($client);

        $client->get('/contact')->assertSessionHasNoErrors()->assertSessionMissingInput('email');
    });

    it('rejects a stale response', function (): void {
        $client = TestClient::boot(httpAppPath());
        $response = failedContactSubmission($client);
        $client->get('/contact');

        $response->assertSessionHasErrors('email');
    })->throws(TestClientException::class, 'this response is stale');
});

describe('toHaveSessionErrors', function (): void {
    it('passes for the flashed error fields', function (): void {
        expect(failedContactSubmission())
            ->toHaveSessionErrors('name')
            ->toHaveSessionErrors(['email' => 'valid email', 'address.city']);
    });

    it('fails with the TestResponse message', function (): void {
        expect(fn () => expect(failedContactSubmission())->toHaveSessionErrors('phone'))
            ->toThrow(
                AssertionFailedError::class,
                'Expected a flashed validation error for [phone] but there was none.',
            );
    });
});
