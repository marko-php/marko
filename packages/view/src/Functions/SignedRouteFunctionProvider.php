<?php

declare(strict_types=1);

namespace Marko\View\Functions;

use Marko\Routing\Signing\UrlSignerInterface;
use Marko\View\Attributes\TemplateFunctions;
use Marko\View\TemplateFunction;
use Marko\View\TemplateFunctionProviderInterface;

/**
 * Adds signedRoute() to every template, backed by UrlSignerInterface:
 *
 *     Latte: <a href={signedRoute('newsletter.unsubscribe', [id: $subscriber->id])}>...</a>
 *     Twig:  <a href="{{ signedRoute('newsletter.unsubscribe', {id: subscriber.id}) }}">...</a>
 *
 * The URL is absolute by default and escaped like any other output.
 */
#[TemplateFunctions]
readonly class SignedRouteFunctionProvider implements TemplateFunctionProviderInterface
{
    public function __construct(
        private UrlSignerInterface $urlSigner,
    ) {}

    public function functions(): array
    {
        return [
            new TemplateFunction('signedRoute', $this->urlSigner->signedRoute(...)),
        ];
    }
}
