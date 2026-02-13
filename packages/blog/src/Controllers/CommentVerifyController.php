<?php

declare(strict_types=1);

namespace Marko\Blog\Controllers;

use InvalidArgumentException;
use Marko\Blog\Contracts\CookieJarInterface;
use Marko\Blog\Services\CommentVerificationServiceInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Http\Response;
use Marko\Session\Contracts\SessionInterface;
use Marko\View\ViewInterface;

readonly class CommentVerifyController
{
    public function __construct(
        private CommentVerificationServiceInterface $verificationService,
        private ViewInterface $view,
        private CookieJarInterface $cookieJar,
        private SessionInterface $session,
    ) {}

    #[Get('/blog/comment/verify/{token}')]
    public function verify(
        string $token,
    ): Response {
        try {
            $result = $this->verificationService->verifyByToken($token);

            $expiresInMinutes = $this->verificationService->getCookieLifetimeDays() * 24 * 60;

            $this->cookieJar->set(
                $this->verificationService->getCookieName(),
                $result->browserToken,
                [
                    'expires' => $expiresInMinutes,
                    'httpOnly' => true,
                    'secure' => true,
                    'sameSite' => 'Lax',
                ],
            );

            $this->session->start();
            $this->session->flash()->add('success', 'Your comment has been verified.');
            $this->session->save();

            $redirectUrl = '/blog/' . $result->postSlug . '#comment-' . $result->commentId;

            return Response::redirect($redirectUrl);
        } catch (InvalidArgumentException) {
            return $this->view->render('blog::comment/verify-error', [
                'statusCode' => 400,
            ]);
        }
    }
}
