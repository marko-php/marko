<?php

declare(strict_types=1);

namespace Marko\Inertia\Response;

use Marko\Core\Event\EventDispatcherInterface;
use Marko\Inertia\Enums\InertiaHeaderEnum;
use Marko\Inertia\Events\InertiaRenderingEvent;
use Marko\Inertia\InertiaConfig;
use Marko\Inertia\InertiaFlashStore;
use Marko\Inertia\Interfaces\RootRendererInterface;
use Marko\Inertia\Props\PropsResolver;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response as HttpResponse;
use Closure;

class Response
{
    /**
     * @param array<string, mixed> $props
     * @param array<string, mixed> $sharedProps
     */
    public function __construct(
        private readonly string $component,
        private readonly array $props,
        private readonly array $sharedProps,
        private readonly InertiaConfig $config,
        private readonly RootRendererInterface $rootRenderer,
        private readonly EventDispatcherInterface $events,
        private readonly PropsResolver $propsResolver,
        private readonly InertiaFlashStore $flash,
        private readonly ?Closure $pageMetadataResolver = null,
    ) {}

    public function toResponse(
        ?Request $request = null,
    ): HttpResponse {
        $request ??= Request::fromGlobals();

        $version = $this->config->version();
        if (
            $this->isInertiaRequest($request)
            && $version !== null
            && ($requestVersion = $request->header(InertiaHeaderEnum::VERSION->value)) !== null
            && $requestVersion !== $version
        ) {
            return $this->location($this->currentUrl($request), $request);
        }

        $event = new InertiaRenderingEvent($request, $this->component, $this->props, $this->sharedProps);
        $this->events->dispatch($event);

        $resolved = $this->propsResolver->resolve(
            $request,
            $this->component,
            array_replace($event->sharedProps, $event->props),
        );

        $page = [
            'component' => $this->component,
            'props' => $resolved->props,
            'url' => $this->currentUrl($request),
            'version' => $version,
        ];

        $page = $this->mergePageMetadata($page, $resolved->metadata);
        $page = $this->mergePageMetadata(
            $page,
            $this->pageMetadataResolver instanceof Closure
                ? (array) ($this->pageMetadataResolver)($request, $this->component, $page['props'])
                : [],
        );

        $flash = $this->flash->pull();
        if ($flash !== []) {
            $page['flash'] = $flash;
        }

        if ($this->config->encryptHistory()) {
            $page['encryptHistory'] = true;
        }

        if ($this->isInertiaRequest($request)) {
            return new HttpResponse(
                body: json_encode($page, JSON_THROW_ON_ERROR),
                statusCode: 200,
                headers: [
                    'Content-Type' => 'application/json',
                    InertiaHeaderEnum::INERTIA->value => 'true',
                    'Vary' => InertiaHeaderEnum::INERTIA->value,
                ],
            );
        }

        return HttpResponse::html($this->rootRenderer->render($page), 200);
    }

    /**
     * @param array<string, mixed> $page
     * @param array<string, mixed> $metadata
     * @return array<string, mixed>
     */
    private function mergePageMetadata(
        array $page,
        array $metadata,
    ): array {
        foreach ($metadata as $key => $value) {
            if ($key === 'props' && is_array($value)) {
                $page['props'] = array_replace_recursive($page['props'], $value);
                continue;
            }

            $page[$key] = $value;
        }

        return $page;
    }

    private function location(
        string $url,
        Request $request,
    ): HttpResponse {
        if ($this->isInertiaRequest($request)) {
            return new HttpResponse(
                body: '',
                statusCode: 409,
                headers: [
                    InertiaHeaderEnum::LOCATION->value => $url,
                    'Vary' => InertiaHeaderEnum::INERTIA->value,
                ],
            );
        }

        return HttpResponse::redirect($url);
    }

    private function currentUrl(
        Request $request,
    ): string {
        $uri = $request->path();
        $query = $request->query();

        if ($query === []) {
            return $uri;
        }

        $queryString = http_build_query($query);

        return $queryString === '' ? $uri : "$uri?$queryString";
    }

    private function isInertiaRequest(
        Request $request,
    ): bool {
        return strtolower((string) $request->header(InertiaHeaderEnum::INERTIA->value, '')) === 'true';
    }
}
