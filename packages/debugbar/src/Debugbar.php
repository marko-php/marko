<?php

declare(strict_types=1);

namespace Marko\Debugbar;

use Closure;
use Marko\Config\ConfigRepositoryInterface;
use Marko\Debugbar\Collectors\CollectorInterface;
use Marko\Debugbar\Collectors\ConfigCollector;
use Marko\Debugbar\Collectors\DatabaseCollector;
use Marko\Debugbar\Collectors\InertiaCollector;
use Marko\Debugbar\Collectors\LogsCollector;
use Marko\Debugbar\Collectors\MemoryCollector;
use Marko\Debugbar\Collectors\MessagesCollector;
use Marko\Debugbar\Collectors\RequestCollector;
use Marko\Debugbar\Collectors\ResponseCollector;
use Marko\Debugbar\Collectors\TimeCollector;
use Marko\Debugbar\Collectors\ViewCollector;
use Marko\Debugbar\Data\CapturedLog;
use Marko\Debugbar\Data\Measure;
use Marko\Debugbar\Data\Message;
use Marko\Debugbar\Data\QueryRecord;
use Marko\Debugbar\Data\ViewRenderRecord;
use Marko\Debugbar\Rendering\HtmlDebugbarRenderer;
use Marko\Debugbar\Storage\DebugbarStorage;
use Throwable;

class Debugbar
{
    private static ?self $current = null;

    private readonly float $startTime;

    private readonly int $startMemory;

    private readonly string $id;

    private bool $booted = false;

    private bool $capturing = false;

    /** @var list<Message> */
    private array $messages = [];

    /** @var array<string, float> */
    private array $openMeasures = [];

    /** @var list<Measure> */
    private array $measures = [];

    /** @var list<QueryRecord> */
    private array $queries = [];

    /** @var list<CapturedLog> */
    private array $logs = [];

    /** @var list<ViewRenderRecord> */
    private array $viewRenders = [];

    public function __construct(
        private readonly ConfigRepositoryInterface $config,
        private readonly ?DebugbarStorage $storage = null,
        private readonly HtmlDebugbarRenderer $renderer = new HtmlDebugbarRenderer(),
    ) {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
        $this->id = bin2hex(random_bytes(8));
        self::$current = $this;
    }

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function forgetCurrent(): void
    {
        self::$current = null;
    }

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        $this->booted = true;

        if (! $this->isEnabled()) {
            return;
        }

        if (PHP_SAPI === 'cli' && ! $this->configBool('debugbar.capture_cli', false)) {
            return;
        }

        $this->capturing = true;

        if (function_exists('header_register_callback')) {
            header_register_callback(function (): void {
                if (! $this->isEnabled() || headers_sent()) {
                    return;
                }

                header('X-Marko-Debugbar: true');
                header('X-Marko-Debugbar-Id: '.$this->id());
                header('X-Marko-Debugbar-Url: '.$this->profilerUrl());
                header('Server-Timing: marko;dur='.$this->durationMs().';desc="Marko"');
            });
        }

        ob_start(fn (string $buffer): string => $this->inject($buffer));
    }

    public function isCapturing(): bool
    {
        return $this->capturing;
    }

    public function isEnabled(): bool
    {
        return $this->configBool('debugbar.enabled', false);
    }

    public function id(): string
    {
        return $this->id;
    }

    public function profilerUrl(): string
    {
        $prefix = trim($this->configString('debugbar.route.prefix', '_debugbar'), '/');

        if ($prefix === '') {
            $prefix = '_debugbar';
        }

        return '/'.$prefix.'/'.$this->id;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function addMessage(
        string $message,
        string $level = 'info',
        array $context = [],
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->messages[] = new Message(
            message: $message,
            level: $level,
            context: $context,
            time: microtime(true),
            trace: $this->messageTrace(),
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(
        string $message,
        array $context = [],
    ): void {
        $this->addMessage($message, 'debug', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(
        string $message,
        array $context = [],
    ): void {
        $this->addMessage($message, 'info', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(
        string $message,
        array $context = [],
    ): void {
        $this->addMessage($message, 'warning', $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(
        string $message,
        array $context = [],
    ): void {
        $this->addMessage($message, 'error', $context);
    }

    public function startMeasure(string $name): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->openMeasures[$name] = microtime(true);
    }

    public function stopMeasure(string $name): void
    {
        if (! isset($this->openMeasures[$name])) {
            return;
        }

        $this->measures[] = new Measure(
            name: $name,
            start: $this->openMeasures[$name],
            end: microtime(true),
        );

        unset($this->openMeasures[$name]);
    }

    public function measure(
        string $name,
        Closure $callback,
    ): mixed {
        $this->startMeasure($name);

        try {
            return $callback();
        } finally {
            $this->stopMeasure($name);
        }
    }

    /**
     * @param array<mixed> $bindings
     */
    public function recordQuery(
        string $type,
        string $sql,
        array $bindings,
        float $start,
        float $durationMs,
        int $rows,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->queries[] = new QueryRecord($type, $sql, $bindings, $start, $durationMs, $rows);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function recordLog(
        string $level,
        string $message,
        array $context = [],
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->logs[] = new CapturedLog($level, $message, $context, microtime(true));
    }

    /**
     * @param list<string> $dataKeys
     */
    public function recordViewRender(
        string $method,
        string $template,
        array $dataKeys,
        float $start,
        float $durationMs,
        int $outputSize,
    ): void {
        if (! $this->isEnabled()) {
            return;
        }

        $this->viewRenders[] = new ViewRenderRecord(
            method: $method,
            template: $template,
            dataKeys: $dataKeys,
            start: $start,
            durationMs: $durationMs,
            outputSize: $outputSize,
        );
    }

    public function inject(string $html): string
    {
        if (! $this->isEnabled()) {
            return $html;
        }

        $dataset = $this->collect($html);
        $this->store($dataset);

        if (! $this->configBool('debugbar.inject', true) || ! $this->looksLikeHtml($html)) {
            return $html;
        }

        $debugbarHtml = $this->renderer->render(
            dataset: $dataset,
            theme: $this->configString('debugbar.theme', 'auto'),
        );

        $bodyPosition = strripos($html, '</body>');

        if ($bodyPosition === false) {
            return $html.$debugbarHtml;
        }

        return substr($html, 0, $bodyPosition).$debugbarHtml.substr($html, $bodyPosition);
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(?string $responseBody = null): array
    {
        $collectors = [
            new MessagesCollector(),
            new TimeCollector(),
            new MemoryCollector(),
            new RequestCollector(),
            new DatabaseCollector(),
            new LogsCollector(),
            new ViewCollector(),
            new ConfigCollector(),
        ];

        $collected = [];

        foreach ($collectors as $collector) {
            if (! $this->collectorEnabled($collector)) {
                continue;
            }

            $collected[$collector->name()] = $collector->collect($this);
        }

        if ($responseBody !== null && $this->configBool('debugbar.collectors.response', true)) {
            $collected['response'] = (new ResponseCollector())->collect($responseBody);
        }

        if ($responseBody !== null && $this->configBool('debugbar.collectors.inertia', true)) {
            $inertia = (new InertiaCollector())->collect($responseBody);

            if ($inertia !== null) {
                $collected['inertia'] = $inertia;
            }
        }

        return [
            'id' => $this->id,
            'stored_at' => date(DATE_ATOM),
            'profiler_url' => $this->profilerUrl(),
            'summary' => [
                'duration_ms' => $this->durationMs(),
                'memory' => $collected['memory']['peak'] ?? null,
                'messages' => count($this->messages),
                'queries' => count($this->queries),
                'logs' => count($this->logs),
                'views' => count($this->viewRenders),
                'method' => $this->serverString('REQUEST_METHOD', 'CLI'),
                'uri' => $this->serverString('REQUEST_URI', '/'),
            ],
            'collectors' => $collected,
        ];
    }

    public function durationMs(): float
    {
        return round((microtime(true) - $this->startTime) * 1000, 2);
    }

    public function startTime(): float
    {
        return $this->startTime;
    }

    public function startMemory(): int
    {
        return $this->startMemory;
    }

    /**
     * @return list<Message>
     */
    public function messages(): array
    {
        return $this->messages;
    }

    /**
     * @return list<Measure>
     */
    public function measures(): array
    {
        return $this->measures;
    }

    /**
     * @return list<QueryRecord>
     */
    public function queries(): array
    {
        return $this->queries;
    }

    /**
     * @return list<CapturedLog>
     */
    public function logs(): array
    {
        return $this->logs;
    }

    /**
     * @return list<ViewRenderRecord>
     */
    public function viewRenders(): array
    {
        return $this->viewRenders;
    }

    public function config(): ConfigRepositoryInterface
    {
        return $this->config;
    }

    public function configBool(
        string $key,
        bool $default,
    ): bool {
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

            return $normalized ?? (bool) $value;
        }

        return $default;
    }

    public function configString(
        string $key,
        string $default,
    ): string {
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            return $default;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    public function configFloat(
        string $key,
        float $default,
    ): float {
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            return $default;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * @param array<mixed> $default
     * @return array<mixed>
     */
    public function configArray(
        string $key,
        array $default,
    ): array {
        try {
            $value = $this->config->get($key);
        } catch (Throwable) {
            return $default;
        }

        return is_array($value) ? $value : $default;
    }

    private function serverString(
        string $key,
        string $default,
    ): string {
        $value = $_SERVER[$key] ?? null;

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }

    private function collectorEnabled(CollectorInterface $collector): bool
    {
        return $this->configBool('debugbar.collectors.'.$collector->name(), false);
    }

    /**
     * @param array<string, mixed> $dataset
     */
    private function store(array $dataset): void
    {
        if ($this->storage === null || ! $this->configBool('debugbar.storage.enabled', true)) {
            return;
        }

        try {
            $this->storage->put($dataset);
        } catch (Throwable) {
            // Debug tooling should never break the application response.
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function messageTrace(): ?array
    {
        if (! $this->configBool('debugbar.options.messages.trace', false)) {
            return null;
        }

        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8) as $frame) {
            $file = $frame['file'] ?? null;
            $line = $frame['line'] ?? null;

            if (! is_string($file) || ! is_int($line) || str_contains($file, '/marko-debugbar/src/')) {
                continue;
            }

            return [
                'file' => $file,
                'line' => $line,
            ];
        }

        return null;
    }

    private function looksLikeHtml(string $html): bool
    {
        $trimmed = ltrim($html);

        if ($trimmed === '') {
            return false;
        }

        if (str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')) {
            return false;
        }

        return stripos($html, '<html') !== false
            || stripos($html, '<body') !== false
            || stripos($html, '</body>') !== false
            || stripos($html, '<!doctype html') !== false;
    }
}
