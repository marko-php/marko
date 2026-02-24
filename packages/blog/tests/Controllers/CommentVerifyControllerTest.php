<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Controllers\CommentVerifyController;

use InvalidArgumentException;
use Marko\Blog\Contracts\CookieJarInterface;
use Marko\Blog\Controllers\CommentVerifyController;
use Marko\Blog\Dto\VerificationResult;
use Marko\Blog\Entity\CommentInterface;
use Marko\Blog\Services\CommentVerificationServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\Testing\Fake\FakeSession;
use Marko\View\ViewInterface;
use ReflectionClass;
use Throwable;

it('verifies comment at GET /blog/comment/verify/{token}', function (): void {
    $reflection = new ReflectionClass(CommentVerifyController::class);
    $method = $reflection->getMethod('verify');
    $attributes = $method->getAttributes(Get::class);

    expect($attributes)->toHaveCount(1);

    $routeAttribute = $attributes[0]->newInstance();
    expect($routeAttribute->path)->toBe('/blog/comment/verify/{token}');
});

it('returns error page when token not found', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenException: new InvalidArgumentException('Invalid verification token'),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $response = $controller->verify('invalid-token');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(400)
        ->and($response->body())->toContain('error');
});

it('returns error page when token is expired', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenException: new InvalidArgumentException('Verification token has expired'),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $response = $controller->verify('expired-token');

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->statusCode())->toBe(400)
        ->and($response->body())->toContain('error');
});

it('marks comment as verified on valid token', function (): void {
    $callCapture = new CallCapture();
    $verificationService = createMockVerificationServiceWithCapture(
        callCapture: $callCapture,
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token-123',
            postSlug: 'test-post',
            commentId: 1,
        ),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    // Verify that verifyByToken was called with the correct token
    expect($callCapture->verifyByTokenCalls)->toHaveCount(1)
        ->and($callCapture->verifyByTokenCalls[0])->toBe('valid-token');
});

it('sets browser cookie with verification token', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token-abc123',
            postSlug: 'test-post',
            commentId: 1,
        ),
        cookieName: 'blog_verified',
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    expect($cookieJar->setCalls)->toHaveCount(1)
        ->and($cookieJar->setCalls[0]['name'])->toBe('blog_verified')
        ->and($cookieJar->setCalls[0]['value'])->toBe('browser-token-abc123');
});

it('uses configured cookie name from BlogConfig', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token',
            postSlug: 'test-post',
            commentId: 1,
        ),
        cookieName: 'my_custom_cookie',
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    expect($cookieJar->setCalls)->toHaveCount(1)
        ->and($cookieJar->setCalls[0]['name'])->toBe('my_custom_cookie');
});

it('uses configured cookie expiry days from BlogConfig', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token',
            postSlug: 'test-post',
            commentId: 1,
        ),
        cookieLifetimeDays: 180,
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    expect($cookieJar->setCalls)->toHaveCount(1)
        ->and($cookieJar->setCalls[0]['options']['expires'])->toBe(180 * 24 * 60);
});

it('sets cookie as HttpOnly and Secure', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token',
            postSlug: 'test-post',
            commentId: 1,
        ),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    expect($cookieJar->setCalls)->toHaveCount(1)
        ->and($cookieJar->setCalls[0]['options']['httpOnly'])->toBeTrue()
        ->and($cookieJar->setCalls[0]['options']['secure'])->toBeTrue()
        ->and($cookieJar->setCalls[0]['options']['sameSite'])->toBe('Lax');
});

it('redirects to post page after verification', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token',
            postSlug: 'my-awesome-post',
            commentId: 42,
        ),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $response = $controller->verify('valid-token');

    expect($response->statusCode())->toBe(302)
        ->and($response->headers()['Location'])->toBe('/blog/my-awesome-post#comment-42');
});

it('starts session before setting flash message', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token',
            postSlug: 'test-post',
            commentId: 1,
        ),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    expect($session->started)->toBeTrue();
});

it('sets success flash message on redirect', function (): void {
    $verificationService = createMockVerificationService(
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token',
            postSlug: 'test-post',
            commentId: 1,
        ),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    expect($session->flash()->peek('success'))->not->toBeEmpty()
        ->and($session->flash()->peek('success')[0])->toContain('verified');
});

it('dispatches CommentVerified event', function (): void {
    // The CommentVerified event is dispatched by the CommentVerificationService
    // when verifyByToken is called successfully. We verify the service is called.
    $callCapture = new CallCapture();
    $verificationService = createMockVerificationServiceWithCapture(
        callCapture: $callCapture,
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token',
            postSlug: 'test-post',
            commentId: 1,
        ),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    // verifyByToken dispatches CommentVerified event internally
    expect($callCapture->verifyByTokenCalls)->toHaveCount(1);
});

it('deletes used email verification token', function (): void {
    // Token deletion is handled by the CommentVerificationService
    // when verifyByToken is called successfully. We verify the service is called.
    $callCapture = new CallCapture();
    $verificationService = createMockVerificationServiceWithCapture(
        callCapture: $callCapture,
        verifyByTokenResult: new VerificationResult(
            browserToken: 'browser-token',
            postSlug: 'test-post',
            commentId: 1,
        ),
    );
    $view = createMockView();
    $cookieJar = new MockCookieJar();
    $session = new FakeSession();

    $controller = new CommentVerifyController(
        verificationService: $verificationService,
        view: $view,
        cookieJar: $cookieJar,
        session: $session,
    );

    $controller->verify('valid-token');

    // verifyByToken deletes the used email verification token internally
    expect($callCapture->verifyByTokenCalls)->toHaveCount(1);
});

// Helper classes

class CallCapture
{
    /** @var array<string> */
    public array $verifyByTokenCalls = [];
}

class MockCookieJar implements CookieJarInterface
{
    /** @var array<array{name: string, value: string, options: array<string, mixed>}> */
    public array $setCalls = [];

    public function set(
        string $name,
        string $value,
        array $options = [],
    ): void {
        $this->setCalls[] = [
            'name' => $name,
            'value' => $value,
            'options' => $options,
        ];
    }
}

// Helper functions

function createMockVerificationService(
    ?VerificationResult $verifyByTokenResult = null,
    ?Throwable $verifyByTokenException = null,
    string $cookieName = 'blog_verified',
    int $cookieLifetimeDays = 365,
): CommentVerificationServiceInterface {
    return new readonly class (
        $verifyByTokenResult,
        $verifyByTokenException,
        $cookieName,
        $cookieLifetimeDays,
    ) implements CommentVerificationServiceInterface
    {
        public function __construct(
            private ?VerificationResult $verifyByTokenResult,
            private ?Throwable $verifyByTokenException,
            private string $cookieName,
            private int $cookieLifetimeDays,
        ) {}

        public function sendVerificationEmail(
            CommentInterface $comment,
        ): string {
            return 'token';
        }

        public function verifyByToken(
            string $token,
        ): VerificationResult {
            if ($this->verifyByTokenException !== null) {
                throw $this->verifyByTokenException;
            }

            return $this->verifyByTokenResult ?? new VerificationResult(
                browserToken: 'browser-token',
                postSlug: 'test-post',
                commentId: 1,
            );
        }

        public function isBrowserTokenValid(
            string $browserToken,
            string $email,
        ): bool {
            return false;
        }

        public function shouldAutoApprove(
            string $email,
            ?string $browserToken,
        ): bool {
            return false;
        }

        public function getCookieName(): string
        {
            return $this->cookieName;
        }

        public function getCookieLifetimeDays(): int
        {
            return $this->cookieLifetimeDays;
        }
    };
}

function createMockVerificationServiceWithCapture(
    CallCapture $callCapture,
    ?VerificationResult $verifyByTokenResult = null,
    ?Throwable $verifyByTokenException = null,
    string $cookieName = 'blog_verified',
    int $cookieLifetimeDays = 365,
): CommentVerificationServiceInterface {
    return new readonly class (
        $callCapture,
        $verifyByTokenResult,
        $verifyByTokenException,
        $cookieName,
        $cookieLifetimeDays,
    ) implements CommentVerificationServiceInterface
    {
        public function __construct(
            private CallCapture $callCapture,
            private ?VerificationResult $verifyByTokenResult,
            private ?Throwable $verifyByTokenException,
            private string $cookieName,
            private int $cookieLifetimeDays,
        ) {}

        public function sendVerificationEmail(
            CommentInterface $comment,
        ): string {
            return 'token';
        }

        public function verifyByToken(
            string $token,
        ): VerificationResult {
            $this->callCapture->verifyByTokenCalls[] = $token;

            if ($this->verifyByTokenException !== null) {
                throw $this->verifyByTokenException;
            }

            return $this->verifyByTokenResult ?? new VerificationResult(
                browserToken: 'browser-token',
                postSlug: 'test-post',
                commentId: 1,
            );
        }

        public function isBrowserTokenValid(
            string $browserToken,
            string $email,
        ): bool {
            return false;
        }

        public function shouldAutoApprove(
            string $email,
            ?string $browserToken,
        ): bool {
            return false;
        }

        public function getCookieName(): string
        {
            return $this->cookieName;
        }

        public function getCookieLifetimeDays(): int
        {
            return $this->cookieLifetimeDays;
        }
    };
}

function createMockView(): ViewInterface
{
    return new class () implements ViewInterface
    {
        public function render(
            string $template,
            array $data = [],
        ): Response {
            return new Response("rendered: $template", $data['statusCode'] ?? 200);
        }

        public function renderToString(
            string $template,
            array $data = [],
        ): string {
            return "rendered: $template";
        }
    };
}
