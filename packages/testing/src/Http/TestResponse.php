<?php

declare(strict_types=1);

namespace Marko\Testing\Http;

use JsonException;
use Marko\Routing\Http\Cookie;
use Marko\Routing\Http\Response;
use Marko\Routing\Http\StreamedResponseInterface;
use Marko\Testing\Exceptions\AssertionFailedException;
use PHPUnit\Framework\Assert;

/**
 * The response to a {@see TestClient} request, with assertions.
 *
 * Every assertion returns $this so they chain, and throws
 * AssertionFailedException on failure with the response status and the start
 * of the body in the message.
 */
class TestResponse
{
    private ?string $streamedContent = null;

    public function __construct(
        private readonly Response $response,
    ) {}

    public function response(): Response
    {
        return $this->response;
    }

    public function status(): int
    {
        return $this->response->statusCode();
    }

    public function body(): string
    {
        return $this->response->body();
    }

    /**
     * The body a StreamedResponseInterface writes when sent (body() is '' for those).
     *
     * Runs sendBody() once under an output buffer and remembers the result;
     * '' when the body was omitted (HEAD). assertSee(), assertDontSee(),
     * assertNoContent() and the JSON assertions read it automatically for
     * streamed responses.
     *
     * @throws AssertionFailedException When the response is not streamed
     */
    public function streamedContent(): string
    {
        if (!$this->response instanceof StreamedResponseInterface) {
            throw AssertionFailedException::notStreamedResponse($this->response::class);
        }

        if ($this->streamedContent !== null) {
            return $this->streamedContent;
        }

        if ($this->response->isBodyOmitted()) {
            return $this->streamedContent = '';
        }

        $response = $this->response->withOutputStreamer(new BufferedOutputStreamer());

        ob_start();

        try {
            $response->sendBody();
        } finally {
            $this->streamedContent = (string) ob_get_clean();
        }

        return $this->streamedContent;
    }

    /**
     * Read a response header; the name is matched case-insensitively.
     */
    public function header(
        string $name,
    ): ?string {
        foreach ($this->response->headers() as $headerName => $value) {
            if (strcasecmp($headerName, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The decoded JSON body, or the value at a dot path (`data.items.0.id`); null when the path is missing.
     *
     * @throws AssertionFailedException when the body is not valid JSON
     */
    public function json(
        ?string $path = null,
    ): mixed {
        $data = $this->decodedJson();

        if ($path === null) {
            return $data;
        }

        [, $value] = $this->resolvePath($data, $path);

        return $value;
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertStatus(
        int $status,
    ): static {
        if ($this->status() !== $status) {
            $this->fail("Expected response status $status but got {$this->status()}.");
        }

        return $this->passed();
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertOk(): static
    {
        return $this->assertStatus(200);
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertCreated(): static
    {
        return $this->assertStatus(201);
    }

    /**
     * Asserts a 204 status and an empty body.
     *
     * @throws AssertionFailedException
     */
    public function assertNoContent(): static
    {
        $this->assertStatus(204);

        if ($this->content() !== '') {
            $this->fail(
                'Expected an empty body for a 204 response but it has ' . strlen($this->content()) . ' bytes.',
            );
        }

        return $this->passed();
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertUnauthorized(): static
    {
        return $this->assertStatus(401);
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertForbidden(): static
    {
        return $this->assertStatus(403);
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertNotFound(): static
    {
        return $this->assertStatus(404);
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertUnprocessable(): static
    {
        return $this->assertStatus(422);
    }

    /**
     * Asserts a 3xx status with a Location header, matching $to exactly when given.
     *
     * @throws AssertionFailedException
     */
    public function assertRedirect(
        ?string $to = null,
    ): static {
        $status = $this->status();

        if ($status < 300 || $status > 399) {
            $this->fail("Expected a redirect status (3xx) but got $status.");
        }

        $location = $this->header('Location');

        if ($location === null) {
            $this->fail("Expected a redirect but the $status response was sent without a Location header.");
        }

        if ($to !== null && $location !== $to) {
            $this->fail("Expected a redirect to [$to] but it redirects to [$location].");
        }

        return $this->passed();
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertHeader(
        string $name,
        ?string $value = null,
    ): static {
        $actual = $this->header($name);

        if ($actual === null) {
            $this->fail("Expected header [$name] to be present but it was not.");
        }

        if ($value !== null && $actual !== $value) {
            $this->fail("Expected header [$name] to be [$value] but it was [$actual].");
        }

        return $this->passed();
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertHeaderMissing(
        string $name,
    ): static {
        $actual = $this->header($name);

        if ($actual !== null) {
            $this->fail("Expected header [$name] to be missing but it was [$actual].");
        }

        return $this->passed();
    }

    /**
     * Asserts the response sets the cookie (a Set-Cookie line), with the given value when one is passed.
     *
     * @throws AssertionFailedException
     */
    public function assertCookie(
        string $name,
        ?string $value = null,
    ): static {
        $cookie = $this->findCookie($name);

        if ($cookie === null) {
            $this->fail("Expected the response to set cookie [$name] but it did not.");
        }

        if ($value !== null && $cookie->value() !== $value) {
            $this->fail("Expected cookie [$name] to be [$value] but it was [{$cookie->value()}].");
        }

        return $this->passed();
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertCookieMissing(
        string $name,
    ): static {
        if ($this->findCookie($name) !== null) {
            $this->fail("Expected the response not to set cookie [$name] but it did.");
        }

        return $this->passed();
    }

    /**
     * Asserts the raw body contains the text. The text is not HTML-escaped first.
     *
     * @throws AssertionFailedException
     */
    public function assertSee(
        string $text,
    ): static {
        if (!str_contains($this->content(), $text)) {
            $this->fail("Expected the response body to contain [$text] but it did not.");
        }

        return $this->passed();
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertDontSee(
        string $text,
    ): static {
        if (str_contains($this->content(), $text)) {
            $this->fail("Expected the response body not to contain [$text] but it did.");
        }

        return $this->passed();
    }

    /**
     * Asserts the JSON body contains the subset: every key in $subset exists with an equal value,
     * compared recursively for nested arrays and strictly for scalars.
     *
     * @param array<mixed> $subset
     * @throws AssertionFailedException
     */
    public function assertJson(
        array $subset,
    ): static {
        if (!$this->containsSubset($this->decodedJson(), $subset)) {
            $this->fail(
                "Expected the JSON response to contain the given subset but it did not.\n\nSubset: "
                . $this->export($subset),
            );
        }

        return $this->passed();
    }

    /**
     * Asserts the JSON body equals the data exactly; the order of object keys does not matter.
     *
     * @param array<mixed> $data
     * @throws AssertionFailedException
     */
    public function assertExactJson(
        array $data,
    ): static {
        if ($this->normalize($this->decodedJson()) !== $this->normalize($data)) {
            $this->fail(
                "Expected the JSON response to equal the given data exactly but it did not.\n\nExpected: "
                . $this->export($data),
            );
        }

        return $this->passed();
    }

    /**
     * Asserts the value at a dot path (`data.items.0.id`) is identical (===) to $expected.
     *
     * @throws AssertionFailedException
     */
    public function assertJsonPath(
        string $path,
        mixed $expected,
    ): static {
        [$exists, $actual] = $this->resolvePath($this->decodedJson(), $path);

        if (!$exists) {
            $this->fail("Expected JSON path [$path] to exist but it does not.");
        }

        if ($actual !== $expected) {
            $this->fail(
                "Expected JSON path [$path] to be {$this->export($expected)} but it was {$this->export($actual)}.",
            );
        }

        return $this->passed();
    }

    /**
     * Asserts the number of items in the JSON body, or in the array at a dot path.
     *
     * @throws AssertionFailedException
     */
    public function assertJsonCount(
        int $count,
        ?string $path = null,
    ): static {
        $data = $this->decodedJson();
        $label = $path ?? '(root)';

        if ($path !== null) {
            [$exists, $data] = $this->resolvePath($data, $path);

            if (!$exists) {
                $this->fail("Expected JSON path [$path] to exist but it does not.");
            }
        }

        if (!is_array($data)) {
            $this->fail("Expected JSON path [$label] to be an array but it was {$this->export($data)}.");
        }

        $actual = count($data);

        if ($actual !== $count) {
            $this->fail("Expected JSON path [$label] to have $count items but it has $actual.");
        }

        return $this->passed();
    }

    /**
     * @throws AssertionFailedException
     */
    public function assertJsonMissingPath(
        string $path,
    ): static {
        [$exists] = $this->resolvePath($this->decodedJson(), $path);

        if ($exists) {
            $this->fail("Expected JSON path [$path] to be missing but it exists.");
        }

        return $this->passed();
    }

    /**
     * Count a passed assertion with PHPUnit when it is loaded, so a test whose only
     * checks are TestResponse assertions is not reported as risky ("did not perform
     * any assertions"). Without PHPUnit this is a no-op.
     */
    private function passed(): static
    {
        if (class_exists(Assert::class)) {
            Assert::assertTrue(true);
        }

        return $this;
    }

    /**
     * @throws AssertionFailedException
     */
    private function fail(
        string $expectation,
    ): never {
        throw AssertionFailedException::responseAssertion($expectation, $this->status(), $this->content());
    }

    /**
     * The body assertions check: the streamed content for a streamed response, body() otherwise.
     *
     * @throws AssertionFailedException
     */
    private function content(): string
    {
        return $this->response instanceof StreamedResponseInterface ? $this->streamedContent() : $this->body();
    }

    /**
     * @throws AssertionFailedException
     */
    private function decodedJson(): mixed
    {
        try {
            return json_decode($this->content(), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw AssertionFailedException::invalidJsonResponse($this->status(), $this->content(), $e->getMessage());
        }
    }

    /**
     * @return array{0: bool, 1: mixed} whether the path exists, and its value
     */
    private function resolvePath(
        mixed $data,
        string $path,
    ): array {
        foreach (explode('.', $path) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return [false, null];
            }

            $data = $data[$segment];
        }

        return [true, $data];
    }

    private function containsSubset(
        mixed $actual,
        mixed $subset,
    ): bool {
        if (!is_array($subset)) {
            return $actual === $subset;
        }

        if (!is_array($actual)) {
            return false;
        }

        foreach ($subset as $key => $value) {
            if (!array_key_exists($key, $actual) || !$this->containsSubset($actual[$key], $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Sort object keys recursively so key order does not affect equality; list order still does.
     */
    private function normalize(
        mixed $data,
    ): mixed {
        if (!is_array($data)) {
            return $data;
        }

        $data = array_map(fn (mixed $value): mixed => $this->normalize($value), $data);

        if (!array_is_list($data)) {
            ksort($data);
        }

        return $data;
    }

    private function export(
        mixed $value,
    ): string {
        if (is_array($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return var_export($value, true);
    }

    private function findCookie(
        string $name,
    ): ?Cookie {
        return array_find(
            $this->response->cookies(),
            fn (Cookie $cookie): bool => $cookie->name() === $name,
        );
    }
}
