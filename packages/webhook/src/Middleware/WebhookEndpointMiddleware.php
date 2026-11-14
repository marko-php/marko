<?php

declare(strict_types=1);

namespace Marko\Webhook\Middleware;

use JsonException;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Config\Exceptions\ConfigException;
use Marko\Config\Exceptions\ConfigNotFoundException;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;
use Marko\Webhook\Attributes\WebhookEndpoint;
use Marko\Webhook\Contracts\WebhookReceiverInterface;
use Marko\Webhook\Exceptions\InvalidSignatureException;
use Marko\Webhook\Exceptions\InvalidWebhookPayloadException;
use Marko\Webhook\Exceptions\InvalidWebhookSecretException;
use Marko\Webhook\Exceptions\WebhookPayloadTooLargeException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;

/**
 * Verifies requests routed to a #[WebhookEndpoint] action before the action runs.
 *
 * Registered as global middleware by marko/webhook. Routes without the attribute pass through
 * untouched. For a webhook endpoint the signing secret is read from the attribute's config key
 * and the request is checked with WebhookReceiverInterface: a bad or missing signature, stale
 * timestamp or replayed delivery answers 401, an oversized body 413, and a body that is not a
 * JSON object or array 400. A missing config key or a too-short secret is a misconfiguration
 * and throws instead of answering.
 */
class WebhookEndpointMiddleware implements MiddlewareInterface
{
    /**
     * Resolved #[WebhookEndpoint] per controller::action (null when none is declared).
     *
     * @var array<string, ?WebhookEndpoint>
     */
    private array $endpoints = [];

    public function __construct(
        private readonly WebhookReceiverInterface $receiver,
        private readonly ConfigRepositoryInterface $config,
    ) {}

    /**
     * @throws ConfigException|ConfigNotFoundException|InvalidWebhookSecretException|JsonException|ReflectionException
     */
    public function handle(
        Request $request,
        callable $next,
    ): Response {
        $controller = $request->controller();
        $action = $request->action();
        $endpoint = ($controller !== null && $action !== null)
            ? $this->endpointFor($controller, $action)
            : null;

        if ($endpoint === null) {
            return $next($request);
        }

        $secret = $this->config->getString($endpoint->secretKey);

        try {
            $this->receiver->receive($request, $secret);
        } catch (WebhookPayloadTooLargeException) {
            return Response::json(['message' => 'Webhook payload too large'], 413);
        } catch (InvalidWebhookPayloadException) {
            return Response::json(['message' => 'Invalid webhook payload'], 400);
        } catch (InvalidSignatureException) {
            return Response::json(['message' => 'Invalid webhook signature'], 401);
        }

        return $next($request);
    }

    /**
     * @throws ReflectionException
     */
    private function endpointFor(
        string $controller,
        string $action,
    ): ?WebhookEndpoint {
        $route = "$controller::$action";

        if (!array_key_exists($route, $this->endpoints)) {
            $this->endpoints[$route] = $this->findEndpoint(new ReflectionClass($controller), $action);
        }

        return $this->endpoints[$route];
    }

    /**
     * The method-level attribute wins over a class-level one. Parent classes are searched too, so a
     * Preference that overrides a webhook action or controller stays verified without repeating the attribute.
     *
     * @param ReflectionClass<object> $controller
     *
     * @throws ReflectionException
     */
    private function findEndpoint(
        ReflectionClass $controller,
        string $action,
    ): ?WebhookEndpoint {
        $hierarchy = [];

        for ($class = $controller; $class !== false; $class = $class->getParentClass()) {
            $hierarchy[] = $class;
        }

        foreach ($hierarchy as $class) {
            if (!$class->hasMethod($action)) {
                continue;
            }

            $attributes = new ReflectionMethod($class->getName(), $action)->getAttributes(WebhookEndpoint::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        foreach ($hierarchy as $class) {
            $attributes = $class->getAttributes(WebhookEndpoint::class);

            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        return null;
    }
}
