<?php

declare(strict_types=1);

use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\OutputStreamer;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponse;
use Marko\Testing\Exceptions\AssertionFailedException;
use Marko\Testing\Exceptions\TestClientException;
use Marko\Testing\Http\TestResponse;
use PHPUnit\Framework\Assert;

function jsonTestResponse(
    mixed $data,
    int $status = 200,
): TestResponse {
    return new TestResponse(Response::json($data, $status));
}

describe('TestResponse status assertions', function (): void {
    it('passes assertStatus for the matching status and returns itself', function (): void {
        $response = new TestResponse(new Response('ok', 202));

        expect($response->assertStatus(202))->toBe($response);
    });

    it('fails assertStatus for a different status', function (): void {
        new TestResponse(new Response('nope', 500))->assertStatus(200);
    })->throws(AssertionFailedException::class, 'Expected response status 200 but got 500.');

    it('passes and fails each named status assertion', function (
        string $method,
        int $status,
    ): void {
        $passing = new TestResponse(new Response($status === 204 ? '' : 'body', $status));
        $failing = new TestResponse(new Response('body', 418));

        expect($passing->$method())->toBe($passing)
            ->and(fn () => $failing->$method())->toThrow(AssertionFailedException::class);
    })->with([
        'assertOk' => ['assertOk', 200],
        'assertCreated' => ['assertCreated', 201],
        'assertNoContent' => ['assertNoContent', 204],
        'assertUnauthorized' => ['assertUnauthorized', 401],
        'assertForbidden' => ['assertForbidden', 403],
        'assertNotFound' => ['assertNotFound', 404],
        'assertUnprocessable' => ['assertUnprocessable', 422],
    ]);

    it('fails assertNoContent when a 204 response has a body', function (): void {
        new TestResponse(new Response('unexpected', 204))->assertNoContent();
    })->throws(AssertionFailedException::class, 'Expected an empty body');
});

describe('TestResponse redirect assertions', function (): void {
    it('passes assertRedirect for any redirect without a target', function (): void {
        $response = new TestResponse(Response::redirect('/dashboard'));

        expect($response->assertRedirect())->toBe($response);
    });

    it('passes assertRedirect when the Location matches the target', function (): void {
        $response = new TestResponse(Response::redirect('/dashboard', 303));

        expect($response->assertRedirect('/dashboard'))->toBe($response);
    });

    it('fails assertRedirect when the Location differs from the target', function (): void {
        new TestResponse(Response::redirect('/login'))->assertRedirect('/dashboard');
    })->throws(AssertionFailedException::class, 'Expected a redirect to [/dashboard] but it redirects to [/login].');

    it('fails assertRedirect for a non-redirect status', function (): void {
        new TestResponse(new Response('page', 200))->assertRedirect();
    })->throws(AssertionFailedException::class, 'Expected a redirect status (3xx) but got 200.');

    it('fails assertRedirect for a redirect status without a Location header', function (): void {
        new TestResponse(new Response('', 302))->assertRedirect();
    })->throws(AssertionFailedException::class, 'without a Location header');
});

describe('TestResponse header assertions', function (): void {
    it('passes assertHeader for a present header, matching the name case-insensitively', function (): void {
        $response = new TestResponse(new Response('', 200, ['X-Trace-Id' => 'abc']));

        expect($response->assertHeader('x-trace-id'))->toBe($response)
            ->and($response->assertHeader('X-Trace-Id', 'abc'))->toBe($response);
    });

    it('fails assertHeader for a missing header', function (): void {
        new TestResponse(new Response())->assertHeader('X-Trace-Id');
    })->throws(AssertionFailedException::class, 'Expected header [X-Trace-Id] to be present but it was not.');

    it('fails assertHeader for a header with a different value', function (): void {
        new TestResponse(new Response('', 200, ['X-Trace-Id' => 'abc']))->assertHeader('X-Trace-Id', 'xyz');
    })->throws(AssertionFailedException::class, 'Expected header [X-Trace-Id] to be [xyz] but it was [abc].');

    it('passes assertHeaderMissing for an absent header', function (): void {
        $response = new TestResponse(new Response());

        expect($response->assertHeaderMissing('X-Trace-Id'))->toBe($response);
    });

    it('fails assertHeaderMissing for a present header', function (): void {
        new TestResponse(new Response('', 200, ['X-Trace-Id' => 'abc']))->assertHeaderMissing('x-trace-id');
    })->throws(AssertionFailedException::class, 'Expected header [x-trace-id] to be missing but it was [abc].');
});

describe('TestResponse cookie assertions', function (): void {
    it('passes assertCookie for a cookie the response sets', function (): void {
        $response = new TestResponse(new Response()->withCookie(new Cookie('locale', 'nl nl')));

        expect($response->assertCookie('locale'))->toBe($response)
            ->and($response->assertCookie('locale', 'nl nl'))->toBe($response);
    });

    it('fails assertCookie for a cookie the response does not set', function (): void {
        new TestResponse(new Response())->assertCookie('locale');
    })->throws(AssertionFailedException::class, 'Expected the response to set cookie [locale] but it did not.');

    it('fails assertCookie for a cookie with a different value', function (): void {
        new TestResponse(new Response()->withCookie(new Cookie('locale', 'en')))->assertCookie('locale', 'nl');
    })->throws(AssertionFailedException::class, 'Expected cookie [locale] to be [nl] but it was [en].');

    it('passes assertCookieMissing for a cookie the response does not set', function (): void {
        $response = new TestResponse(new Response());

        expect($response->assertCookieMissing('locale'))->toBe($response);
    });

    it('fails assertCookieMissing for a cookie the response sets', function (): void {
        new TestResponse(new Response()->withCookie(new Cookie('locale', 'en')))->assertCookieMissing('locale');
    })->throws(AssertionFailedException::class, 'Expected the response not to set cookie [locale] but it did.');
});

describe('TestResponse body assertions', function (): void {
    it('passes assertSee when the body contains the text', function (): void {
        $response = new TestResponse(new Response('<h1>Welkom</h1>'));

        expect($response->assertSee('Welkom'))->toBe($response);
    });

    it('fails assertSee when the body lacks the text', function (): void {
        new TestResponse(new Response('<h1>Welcome</h1>'))->assertSee('Welkom');
    })->throws(AssertionFailedException::class, 'Expected the response body to contain [Welkom] but it did not.');

    it('passes assertDontSee when the body lacks the text', function (): void {
        $response = new TestResponse(new Response('<h1>Welcome</h1>'));

        expect($response->assertDontSee('Welkom'))->toBe($response);
    });

    it('fails assertDontSee when the body contains the text', function (): void {
        new TestResponse(new Response('<h1>Welkom</h1>'))->assertDontSee('Welkom');
    })->throws(AssertionFailedException::class, 'Expected the response body not to contain [Welkom] but it did.');
});

describe('TestResponse JSON assertions', function (): void {
    it('passes assertJson for a nested subset', function (): void {
        $response = jsonTestResponse(['data' => ['id' => 42, 'status' => 'live', 'tags' => ['a']], 'meta' => []]);

        expect($response->assertJson(['data' => ['status' => 'live']]))->toBe($response);
    });

    it('fails assertJson when a value in the subset differs', function (): void {
        jsonTestResponse(['data' => ['status' => 'draft']])->assertJson(['data' => ['status' => 'live']]);
    })->throws(
        AssertionFailedException::class,
        'Expected the JSON response to contain the given subset but it did not.',
    );

    it('fails assertJson when a key in the subset is missing', function (): void {
        jsonTestResponse(['data' => []])->assertJson(['data' => ['status' => 'live']]);
    })->throws(AssertionFailedException::class, 'Expected the JSON response to contain the given subset');

    it('passes assertExactJson for equal JSON regardless of key order', function (): void {
        $response = jsonTestResponse(['b' => 2, 'a' => ['y' => 1, 'x' => 0]]);

        expect($response->assertExactJson(['a' => ['x' => 0, 'y' => 1], 'b' => 2]))->toBe($response);
    });

    it('fails assertExactJson when the JSON has extra keys', function (): void {
        jsonTestResponse(['a' => 1, 'b' => 2])->assertExactJson(['a' => 1]);
    })->throws(AssertionFailedException::class, 'Expected the JSON response to equal the given data exactly');

    it('passes assertJsonPath for a value at a dot path, including list indexes', function (): void {
        $response = jsonTestResponse(['data' => ['status' => 'live', 'items' => [['id' => 7]]]]);

        expect($response->assertJsonPath('data.status', 'live'))->toBe($response)
            ->and($response->assertJsonPath('data.items.0.id', 7))->toBe($response);
    });

    it('fails assertJsonPath for a different value', function (): void {
        jsonTestResponse(['data' => ['status' => 'draft']])->assertJsonPath('data.status', 'live');
    })->throws(AssertionFailedException::class, "Expected JSON path [data.status] to be 'live' but it was 'draft'.");

    it('compares assertJsonPath strictly', function (): void {
        jsonTestResponse(['id' => 42])->assertJsonPath('id', '42');
    })->throws(AssertionFailedException::class, "Expected JSON path [id] to be '42' but it was 42.");

    it('fails assertJsonPath for a missing path', function (): void {
        jsonTestResponse(['data' => []])->assertJsonPath('data.status', 'live');
    })->throws(AssertionFailedException::class, 'Expected JSON path [data.status] to exist but it does not.');

    it('passes assertJsonCount for the root and for a path', function (): void {
        $response = jsonTestResponse(['data' => [1, 2, 3]]);

        expect($response->assertJsonCount(1))->toBe($response)
            ->and($response->assertJsonCount(3, 'data'))->toBe($response);
    });

    it('fails assertJsonCount for a different count', function (): void {
        jsonTestResponse(['data' => [1, 2, 3]])->assertJsonCount(2, 'data');
    })->throws(AssertionFailedException::class, 'Expected JSON path [data] to have 2 items but it has 3.');

    it('fails assertJsonCount when the path is not an array', function (): void {
        jsonTestResponse(['data' => 'text'])->assertJsonCount(1, 'data');
    })->throws(AssertionFailedException::class, 'Expected JSON path [data] to be an array');

    it('passes assertJsonMissingPath for an absent path', function (): void {
        $response = jsonTestResponse(['data' => ['id' => 1]]);

        expect($response->assertJsonMissingPath('data.password'))->toBe($response);
    });

    it('fails assertJsonMissingPath for a present path, even when its value is null', function (): void {
        jsonTestResponse(['data' => ['password' => null]])->assertJsonMissingPath('data.password');
    })->throws(AssertionFailedException::class, 'Expected JSON path [data.password] to be missing but it exists.');

    it('fails a JSON assertion loudly when the body is not JSON', function (): void {
        new TestResponse(new Response('<html>oops</html>', 500))->assertJsonPath('id', 1);
    })->throws(AssertionFailedException::class, 'Expected the response body to be valid JSON');
});

describe('TestResponse accessors', function (): void {
    it('exposes the status, body, decoded JSON and wrapped response', function (): void {
        $inner = Response::json(['data' => ['id' => 5]], 201);
        $response = new TestResponse($inner);

        expect($response->status())->toBe(201)
            ->and($response->body())->toBe('{"data":{"id":5}}')
            ->and($response->json())->toBe(['data' => ['id' => 5]])
            ->and($response->json('data.id'))->toBe(5)
            ->and($response->json('data.missing'))->toBeNull()
            ->and($response->response())->toBe($inner);
    });

    it('reads a header case-insensitively', function (): void {
        $response = new TestResponse(new Response('', 200, ['Content-Type' => 'text/plain']));

        expect($response->header('content-type'))->toBe('text/plain')
            ->and($response->header('X-Missing'))->toBeNull();
    });
});

it('counts each passed assertion with PHPUnit so the test is not risky', function (): void {
    $response = new TestResponse(new Response('ok'));
    $before = Assert::getCount();

    $response->assertOk()->assertSee('ok');

    expect(Assert::getCount())->toBe($before + 2);
});

describe('TestResponse failure messages', function (): void {
    it('includes the status and a body excerpt in failure messages', function (): void {
        $response = new TestResponse(new Response('Something broke in the controller', 500));

        try {
            $response->assertOk();
            $this->fail('assertOk should have thrown');
        } catch (AssertionFailedException $e) {
            expect($e->getMessage())->toContain('Response status: 500')
                ->toContain('Response body: Something broke in the controller');
        }
    });

    it('truncates a long body in the failure message', function (): void {
        $response = new TestResponse(new Response(str_repeat('x', 600), 500));

        try {
            $response->assertOk();
            $this->fail('assertOk should have thrown');
        } catch (AssertionFailedException $e) {
            expect($e->getMessage())->toContain(str_repeat('x', 500) . '... (100 more bytes)')
                ->not->toContain(str_repeat('x', 501));
        }
    });
});

describe('TestResponse streamed content', function (): void {
    it('runs sendBody() under an output buffer and returns what it wrote', function (): void {
        $response = new TestResponse(new StreamedResponse(function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write('streamed ');
            $outputStreamer->write('body');
        }));

        expect($response->body())->toBe('')
            ->and($response->streamedContent())->toBe('streamed body');
    });

    it('runs sendBody() only once', function (): void {
        $calls = 0;
        $response = new TestResponse(
            new StreamedResponse(function (OutputStreamer $outputStreamer) use (&$calls): void {
                $calls++;
                $outputStreamer->write('once');
            }),
        );

        $response->streamedContent();
        $response->assertSee('once');

        expect($calls)->toBe(1);
    });

    it('uses the streamed content for assertSee and assertDontSee', function (): void {
        $response = new TestResponse(new StreamedResponse(function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write('needle in a stream');
        }));

        expect($response->assertSee('needle')->assertDontSee('haystack'))->toBe($response)
            ->and(fn () => $response->assertSee('haystack'))->toThrow(
                AssertionFailedException::class,
                'needle in a stream',
            );
    });

    it('uses the streamed content for JSON assertions', function (): void {
        $response = new TestResponse(new StreamedResponse(function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write('{"data":');
            $outputStreamer->write('{"id":5}}');
        }));

        expect($response->assertJsonPath('data.id', 5))->toBe($response);
    });

    it('returns an empty string when the body was omitted', function (): void {
        $response = new TestResponse(new StreamedResponse(function (OutputStreamer $outputStreamer): void {
            $outputStreamer->write('never');
        })->withoutBody());

        expect($response->streamedContent())->toBe('');
    });

    it('throws for a response that is not streamed', function (): void {
        new TestResponse(new Response('plain'))->streamedContent();
    })->throws(AssertionFailedException::class, 'Expected a streamed response but got Marko\Routing\Http\Response.');
});

describe('TestResponse assertDownload', function (): void {
    it('passes for an attachment, with and without a filename', function (): void {
        $response = new TestResponse(new Response('a,b', headers: [
            'Content-Disposition' => 'attachment; filename="report.csv"',
        ]));

        expect($response->assertDownload())->toBe($response)
            ->and($response->assertDownload('report.csv'))->toBe($response);
    });

    it('matches a non-ASCII filename sent as filename*', function (): void {
        $response = new TestResponse(new Response('', headers: [
            'Content-Disposition' => "attachment; filename=\"r_sum_.pdf\"; filename*=UTF-8''r%C3%A9sum%C3%A9.pdf",
        ]));

        expect($response->assertDownload('résumé.pdf'))->toBe($response);
    });

    it('fails without a Content-Disposition header', function (): void {
        new TestResponse(new Response('body'))->assertDownload();
    })->throws(
        AssertionFailedException::class,
        'Expected a download but the response has no Content-Disposition header.',
    );

    it('fails for an inline disposition', function (): void {
        new TestResponse(new Response('', headers: ['Content-Disposition' => 'inline; filename="a.pdf"']))
            ->assertDownload();
    })->throws(
        AssertionFailedException::class,
        'Expected a download (Content-Disposition: attachment) but it is [inline; filename="a.pdf"].',
    );

    it('fails for a different filename', function (): void {
        new TestResponse(new Response('', headers: ['Content-Disposition' => 'attachment; filename="a.pdf"']))
            ->assertDownload('b.pdf');
    })->throws(AssertionFailedException::class, 'Expected a download named [b.pdf] but it is named [a.pdf].');

    it('fails when a filename is expected and none is sent', function (): void {
        new TestResponse(new Response('', headers: ['Content-Disposition' => 'attachment']))
            ->assertDownload('b.pdf');
    })->throws(AssertionFailedException::class, 'Expected a download named [b.pdf] but it has no filename.');

    it('fails for a malformed header', function (): void {
        new TestResponse(new Response('', headers: ['Content-Disposition' => 'form-data; name="x"']))
            ->assertDownload();
    })->throws(
        AssertionFailedException::class,
        'Expected a download but the Content-Disposition header [form-data; name="x"] is malformed',
    );
});

describe('TestResponse JSON validation error assertions', function (): void {
    it('passes for fields with errors, by name or with a message', function (): void {
        $response = jsonTestResponse(['message' => 'Invalid', 'errors' => [
            'name' => ['The name field is required.'],
            'items.0.sku' => ['The items.0.sku field is required.'],
        ]], 422);

        expect($response->assertJsonValidationErrors('name'))->toBe($response)
            ->and($response->assertJsonValidationErrors(['name', 'items.0.sku']))->toBe($response)
            ->and($response->assertJsonValidationErrors(['name' => 'The name field is required.']))->toBe($response);
    });

    it('fails for a message the field does not have', function (): void {
        jsonTestResponse(['errors' => ['name' => ['The name field is required.']]], 422)
            ->assertJsonValidationErrors(['name' => 'Too long.']);
    })->throws(
        AssertionFailedException::class,
        'Expected JSON validation error [Too long.] for [name] but its errors were ["The name field is required."].',
    );

    it('fails when the body has no errors object', function (): void {
        jsonTestResponse(['message' => 'Server Error'], 500)->assertJsonValidationErrors('name');
    })->throws(AssertionFailedException::class, 'Expected a JSON validation error for [name] but there was none.');

    it('passes assertJsonMissingValidationErrors for other fields and fails for a listed one', function (): void {
        $response = jsonTestResponse(['errors' => ['name' => ['Required.']]], 422);

        expect($response->assertJsonMissingValidationErrors(['email', 'phone']))->toBe($response)
            ->and(fn () => $response->assertJsonMissingValidationErrors('name'))
            ->toThrow(
                AssertionFailedException::class,
                'Expected no JSON validation error for [name] but it has ["Required."].',
            );
    });

    it('fails assertJsonMissingValidationErrors without fields when any error exists', function (): void {
        jsonTestResponse(['errors' => ['name' => ['Required.']]], 422)->assertJsonMissingValidationErrors();
    })->throws(
        AssertionFailedException::class,
        'Expected no JSON validation errors but there were: {"name":["Required."]}',
    );

    it('passes assertJsonMissingValidationErrors for an empty body', function (): void {
        $response = new TestResponse(new Response('', 204));

        expect($response->assertJsonMissingValidationErrors())->toBe($response);
    });
});

describe('TestResponse session assertions without a TestClient', function (): void {
    it('throws when the response was not made by a TestClient', function (): void {
        new TestResponse(new Response('ok'))->assertSessionHas('plan');
    })->throws(TestClientException::class, 'This TestResponse has no session to assert on');
});
