<?php

declare(strict_types=1);

use Marko\Routing\Exceptions\InvalidSignatureException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Routing\Middleware\ValidSignatureMiddleware;
use Marko\Routing\Signing\UrlSignerInterface;

function fakeUrlSigner(
    ?InvalidSignatureException $failure,
): UrlSignerInterface {
    return new readonly class ($failure) implements UrlSignerInterface
    {
        public function __construct(
            private ?InvalidSignatureException $failure,
        ) {}

        public function signedRoute(
            string $name,
            array $parameters = [],
            ?DateTimeImmutable $expiresAt = null,
            bool $absolute = true,
        ): string {
            return '';
        }

        public function sign(
            string $url,
            ?DateTimeImmutable $expiresAt = null,
        ): string {
            return $url;
        }

        public function hasValidSignature(
            Request $request,
        ): bool {
            return $this->failure === null;
        }

        public function assertValidSignature(
            Request $request,
        ): void {
            if ($this->failure !== null) {
                throw $this->failure;
            }
        }
    };
}

describe('ValidSignatureMiddleware', function (): void {
    it('is a middleware', function (): void {
        expect(new ValidSignatureMiddleware(fakeUrlSigner(null)))->toBeInstanceOf(MiddlewareInterface::class);
    });

    it('passes a validly signed request to the next handler', function (): void {
        $middleware = new ValidSignatureMiddleware(fakeUrlSigner(null));

        $response = $middleware->handle(new Request(), fn (Request $request): Response => new Response('ok'));

        expect($response->body())->toBe('ok');
    });

    it('throws a 403 saying the link is invalid', function (): void {
        $middleware = new ValidSignatureMiddleware(fakeUrlSigner(InvalidSignatureException::invalid()));

        expect(fn () => $middleware->handle(new Request(), fn (): Response => new Response('ok')))
            ->toThrow(InvalidSignatureException::class, 'This link is invalid.');
    });

    it('throws a 403 saying the link has expired', function (): void {
        $middleware = new ValidSignatureMiddleware(fakeUrlSigner(InvalidSignatureException::expired()));

        try {
            $middleware->handle(new Request(), fn (): Response => new Response('ok'));
        } catch (InvalidSignatureException $exception) {
            expect($exception->getStatusCode())->toBe(403)
                ->and($exception->getMessage())->toBe('This link has expired.');

            return;
        }

        throw new RuntimeException('Expected an InvalidSignatureException.');
    });
});
