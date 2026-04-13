<?php

declare(strict_types=1);

namespace Marko\Inertia;

use Marko\Inertia\Enums\InertiaHeaderEnum;
use Marko\Inertia\Interfaces\InertiaInterface;
use Marko\Inertia\Interfaces\ProvidesScrollMetadata;
use Marko\Inertia\Props\AlwaysProp;
use Marko\Inertia\Props\DeferProp;
use Marko\Inertia\Props\MergeProp;
use Marko\Inertia\Props\OnceProp;
use Marko\Inertia\Props\OptionalProp;
use Marko\Inertia\Props\ScrollProp;
use Marko\Inertia\Response\ResponseFactory;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class Inertia implements InertiaInterface
{
    public function __construct(
        private readonly ResponseFactory $responses,
    ) {}

    public function render(
        string $component,
        array $props = [],
        ?Request $request = null,
    ): Response {
        return $this->responses->render($component, $props)->toResponse($request);
    }

    public function location(
        string $url,
        ?Request $request = null,
    ): Response {
        $request ??= Request::fromGlobals();

        if ($this->isInertiaRequest($request)) {
            return new Response(
                body: '',
                statusCode: 409,
                headers: [
                    InertiaHeaderEnum::LOCATION->value => $url,
                    'Vary' => InertiaHeaderEnum::INERTIA->value,
                ],
            );
        }

        return Response::redirect($url);
    }

    public function share(
        string|array $key,
        mixed $value = null,
    ): void {
        $this->responses->share($key, $value);
    }

    public function flash(
        string|array $key,
        mixed $value = null,
    ): void {
        $this->responses->flash($key, $value);
    }

    public function flushShared(): void
    {
        $this->responses->flushShared();
    }

    public function shared(): array
    {
        return $this->responses->shared();
    }

    public function optional(
        mixed $value,
    ): OptionalProp {
        return $this->responses->optional($value);
    }

    public function always(
        mixed $value,
    ): AlwaysProp {
        return $this->responses->always($value);
    }

    public function defer(
        mixed $value,
        string $group = 'default',
    ): DeferProp {
        return $this->responses->defer($value, $group);
    }

    public function merge(
        mixed $value,
    ): MergeProp {
        return $this->responses->merge($value);
    }

    public function deepMerge(
        mixed $value,
    ): MergeProp {
        return $this->responses->deepMerge($value);
    }

    public function once(
        mixed $value,
        ?string $key = null,
    ): OnceProp {
        return $this->responses->once($value, $key);
    }

    public function scroll(
        mixed $value,
        string $wrapper = 'data',
        ProvidesScrollMetadata|callable|array|null $metadata = null,
    ): ScrollProp {
        return $this->responses->scroll($value, $wrapper, $metadata);
    }

    private function isInertiaRequest(
        Request $request,
    ): bool {
        return strtolower(
            (string) $request->header(InertiaHeaderEnum::INERTIA->value, ''),
        ) === 'true';
    }
}
