<?php

declare(strict_types=1);

namespace Marko\Debugbar\Plugins;

use Marko\Core\Attributes\After;
use Marko\Core\Attributes\Before;
use Marko\Core\Attributes\Plugin;
use Marko\Debugbar\Debugbar;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

#[Plugin(target: ViewInterface::class)]
class ViewPlugin
{
    /**
     * @var array<string, list<float>>
     */
    private array $started = [];

    public function __construct(
        private readonly Debugbar $debugbar,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    #[Before(method: 'render')]
    public function beforeRender(
        string $template,
        array $data = [],
    ): void {
        $this->start('render', $template);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[After(method: 'render')]
    public function afterRender(
        Response $result,
        string $template,
        array $data = [],
    ): Response {
        $this->finish('render', $template, $data, strlen($result->body()));

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     */
    #[Before(method: 'renderToString')]
    public function beforeRenderToString(
        string $template,
        array $data = [],
    ): void {
        $this->start('renderToString', $template);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[After(method: 'renderToString')]
    public function afterRenderToString(
        string $result,
        string $template,
        array $data = [],
    ): string {
        $this->finish('renderToString', $template, $data, strlen($result));

        return $result;
    }

    private function start(
        string $method,
        string $template,
    ): void {
        if (! $this->debugbar->isEnabled()) {
            return;
        }

        $this->started[$this->key($method, $template)][] = microtime(true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function finish(
        string $method,
        string $template,
        array $data,
        int $outputSize,
    ): void {
        if (! $this->debugbar->isEnabled()) {
            return;
        }

        $key = $this->key($method, $template);
        $start = array_pop($this->started[$key]);

        if (! is_float($start)) {
            return;
        }

        $this->debugbar->recordViewRender(
            method: $method,
            template: $template,
            dataKeys: array_values(array_filter(array_keys($data), 'is_string')),
            start: $start,
            durationMs: round((microtime(true) - $start) * 1000, 2),
            outputSize: $outputSize,
        );
    }

    private function key(
        string $method,
        string $template,
    ): string {
        return $method.':'.md5($template);
    }
}
