<?php

declare(strict_types=1);

namespace Marko\AdminPanel\Tests\Feature\LoginRerenderCsrf;

use Marko\Admin\Config\AdminConfigInterface;
use Marko\AdminPanel\Controller\LoginController;
use Marko\AdminPanel\Tests\Fixtures\FixedAdminGuardResolver;
use Marko\Encryption\Contracts\EncryptorInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\CsrfTokenManager;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Session\Config\SessionConfig;
use Marko\Testing\Fake\FakeConfigRepository;
use Marko\Testing\Fake\FakeGuard;
use Marko\Testing\Fake\FakeSession;
use Marko\View\ViewInterface;

/**
 * Renders the hidden _token field the way the latte and twig login templates
 * do: a missing csrfToken falls back to an empty value.
 */
class TokenFieldView implements ViewInterface
{
    public function render(
        string $template,
        array $data = [],
    ): Response {
        return Response::html($this->renderToString($template, $data));
    }

    public function renderToString(
        string $template,
        array $data = [],
    ): string {
        $token = htmlspecialchars((string) ($data['csrfToken'] ?? ''), ENT_QUOTES);
        $error = htmlspecialchars((string) ($data['error'] ?? ''), ENT_QUOTES);

        return '<form method="post"><div role="alert">' . $error . '</div>'
            . '<input type="hidden" name="_token" value="' . $token . '"></form>';
    }
}

readonly class PlainEncryptor implements EncryptorInterface
{
    public function encrypt(
        string $value,
        string $aad = '',
    ): string {
        return bin2hex($value);
    }

    public function decrypt(
        string $encrypted,
        string $aad = '',
    ): string {
        return (string) hex2bin($encrypted);
    }
}

readonly class StaticAdminConfig implements AdminConfigInterface
{
    public function getRoutePrefix(): string
    {
        return '/admin';
    }

    public function getName(): string
    {
        return 'Admin';
    }
}

/**
 * Accepts only the admin@example.com / correct-password pair.
 */
class PasswordCheckingGuard extends FakeGuard
{
    public function attempt(
        array $credentials,
        bool $remember = false,
    ): bool {
        return $credentials['email'] === 'admin@example.com'
            && $credentials['password'] === 'correct-password';
    }
}

function tokenFromForm(
    Response $response,
): string {
    preg_match('/name="_token" value="([^"]*)"/', $response->body(), $matches);

    return $matches[1] ?? '';
}

it(
    're-renders the login form with the session CSRF token after a failed login so the retry is accepted',
    function (): void {
        $session = new FakeSession();
        $session->start();
        $tokenManager = new CsrfTokenManager($session, new PlainEncryptor());
        $csrf = new CsrfMiddleware(
            tokenManager: $tokenManager,
            session: $session,
            sessionConfig: new SessionConfig(new FakeConfigRepository([
                'session.cookie.name' => 'marko_session',
                'session.cookie.path' => '/',
                'session.cookie.domain' => '',
                'session.cookie.secure' => true,
            ])),
        );
        $controller = new LoginController(
            view: new TokenFieldView(),
            adminGuard: new FixedAdminGuardResolver(new PasswordCheckingGuard(name: 'admin')),
            adminConfig: new StaticAdminConfig(),
            csrfTokenManager: $tokenManager,
        );

        $form = $csrf->handle(
            new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/admin/login']),
            fn (Request $request): Response => $controller->showLoginForm($request),
        );
        $initialToken = tokenFromForm($form);

        $failed = $csrf->handle(
            new Request(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/login'],
                post: ['_token' => $initialToken, 'email' => 'admin@example.com', 'password' => 'wrong-password'],
            ),
            fn (Request $request): Response => $controller->authenticate($request),
        );
        $rerenderedToken = tokenFromForm($failed);

        $retry = $csrf->handle(
            new Request(
                server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/admin/login'],
                post: ['_token' => $rerenderedToken, 'email' => 'admin@example.com', 'password' => 'correct-password'],
            ),
            fn (Request $request): Response => $controller->authenticate($request),
        );

        expect($initialToken)->not->toBe('')
            ->and($failed->statusCode())->toBe(200)
            ->and($failed->body())->toContain('Invalid email or password.')
            ->and($rerenderedToken)->toBe($initialToken)
            ->and($retry->statusCode())->toBe(302)
            ->and($retry->headers()['Location'])->toBe('/admin');
    },
);
