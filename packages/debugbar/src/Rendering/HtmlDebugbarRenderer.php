<?php

declare(strict_types=1);

namespace Marko\Debugbar\Rendering;

use JsonException;

class HtmlDebugbarRenderer
{
    /**
     * @param array<string, mixed> $dataset
     */
    public function render(
        array $dataset,
        string $theme,
    ): string {
        $id = $this->escape($this->stringValue($dataset['id'] ?? null, 'marko'));
        $summary = $this->stringKeyArray($dataset['summary'] ?? null);
        $collectors = $this->stringKeyArray($dataset['collectors'] ?? null);

        $tabs = '';
        $panels = '';
        $first = true;

        foreach ($collectors as $name => $collectorData) {
            $collector = $this->stringKeyArray($collectorData);

            if ($collector === []) {
                continue;
            }

            $active = $first ? ' data-active="true" aria-selected="true"' : ' aria-selected="false"';
            $hidden = $first ? '' : ' hidden';
            $label = $this->escape($this->stringValue($collector['label'] ?? null, $name));
            $badge = isset($collector['badge']) ? '<span>'.$this->escape(
                $this->stringValue($collector['badge'], ''),
            ).'</span>' : '';
            $safeName = $this->escape((string) $name);

            $tabs .= "<button type=\"button\" role=\"tab\" data-marko-debugbar-tab=\"$safeName\"$active>$label$badge</button>";
            $panels .= "<section data-marko-debugbar-panel=\"$safeName\"$hidden>".$this->renderCollector(
                $name,
                $collector,
            ).'</section>';
            $first = false;
        }

        $duration = $this->escape($this->stringValue($summary['duration_ms'] ?? null, '0')).' ms';
        $memory = $this->escape($this->stringValue($summary['memory'] ?? null, 'n/a'));
        $messages = $this->escape($this->stringValue($summary['messages'] ?? null, '0'));
        $queries = $this->escape($this->stringValue($summary['queries'] ?? null, '0'));
        $logs = $this->escape($this->stringValue($summary['logs'] ?? null, '0'));
        $method = $this->escape($this->stringValue($summary['method'] ?? null, 'CLI'));
        $uri = $this->escape($this->stringValue($summary['uri'] ?? null, '/'));
        $profilerUrl = $this->escape($this->stringValue($dataset['profiler_url'] ?? null, '/_debugbar/'.$id));
        $themeAttribute = $this->escape($theme);

        return <<<HTML

<div id="marko-debugbar-$id" class="marko-debugbar" data-marko-debugbar data-marko-debugbar-state="collapsed" data-theme="$themeAttribute">
<style>
.marko-debugbar{position:fixed;left:0;right:0;bottom:0;z-index:2147483000;font:12px/1.45 ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#dbe4ef;letter-spacing:0}
.marko-debugbar *{box-sizing:border-box}
.marko-debugbar[data-theme=light]{color:#17202e}
.marko-debugbar-bar{display:flex;align-items:center;gap:8px;min-height:40px;padding:5px 10px;background:#101722;border-top:1px solid #2f3d4f;box-shadow:0 -8px 24px rgba(0,0,0,.28)}
.marko-debugbar[data-theme=light] .marko-debugbar-bar{background:#f8fafc;border-top-color:#cbd5e1;box-shadow:0 -4px 14px rgba(15,23,42,.12)}
.marko-debugbar-brand{display:inline-flex;align-items:center;justify-content:center;min-width:30px;height:26px;border:1px solid #265b73;border-radius:4px;background:#0b2230;color:#7dd3fc;font-weight:800}
.marko-debugbar[data-theme=light] .marko-debugbar-brand{border-color:#bae6fd;background:#e0f2fe;color:#0369a1}
.marko-debugbar-summary{display:flex;align-items:center;gap:6px;min-width:0;white-space:nowrap;overflow:hidden}
.marko-debugbar-uri{min-width:80px;overflow:hidden;text-overflow:ellipsis;color:#c8d6e6}
.marko-debugbar[data-theme=light] .marko-debugbar-uri{color:#334155}
.marko-debugbar-pill{display:inline-flex;align-items:center;min-height:24px;border:1px solid #3b4b60;background:#182233;border-radius:4px;padding:2px 7px;color:inherit}
.marko-debugbar[data-theme=light] .marko-debugbar-pill{border-color:#cbd5e1;background:#fff}
.marko-debugbar-tabs{display:flex;align-items:center;gap:2px;margin-left:auto;overflow:auto;scrollbar-width:thin}
.marko-debugbar button{font:inherit;color:inherit;border:0;background:transparent;border-radius:4px;padding:5px 8px;cursor:pointer}
.marko-debugbar a{color:inherit;text-decoration:none}
.marko-debugbar button:hover,.marko-debugbar a:hover{background:#1f2b3d}
.marko-debugbar[data-theme=light] button:hover,.marko-debugbar[data-theme=light] a:hover{background:#e2e8f0}
.marko-debugbar button[data-active=true]{background:#263449;color:#fff}
.marko-debugbar[data-theme=light] button[data-active=true]{background:#e2e8f0}
.marko-debugbar button span{margin-left:5px;color:#93c5fd}
.marko-debugbar-open{border:1px solid #3b4b60;border-radius:4px;padding:5px 8px;white-space:nowrap}
.marko-debugbar[data-theme=light] .marko-debugbar-open{border-color:#cbd5e1}
.marko-debugbar-toggle{min-width:68px;margin-left:2px;border:1px solid #3b4b60!important;background:#162131!important}
.marko-debugbar[data-theme=light] .marko-debugbar-toggle{border-color:#cbd5e1!important;background:#fff!important}
.marko-debugbar-close{width:28px;height:28px;padding:0!important;color:#93a8bf!important}
.marko-debugbar-panel{max-height:46vh;overflow:auto;background:#0b1018;border-top:1px solid #2f3d4f;padding:12px}
.marko-debugbar[data-theme=light] .marko-debugbar-panel{background:#fff;border-top-color:#cbd5e1}
.marko-debugbar[data-marko-debugbar-state=collapsed] .marko-debugbar-panel{display:none}
.marko-debugbar-panel[hidden]{display:none}
.marko-debugbar table{width:100%;border-collapse:collapse}
.marko-debugbar th,.marko-debugbar td{text-align:left;vertical-align:top;border-bottom:1px solid #223044;padding:6px 8px}
.marko-debugbar[data-theme=light] th,.marko-debugbar[data-theme=light] td{border-bottom-color:#e2e8f0}
.marko-debugbar th{color:#93c5fd;font-weight:600}
.marko-debugbar pre{margin:0;white-space:pre-wrap;word-break:break-word;color:inherit;font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace}
.marko-debugbar-level{font-weight:700;text-transform:uppercase}
@media (max-width:760px){.marko-debugbar-bar{align-items:flex-start;flex-wrap:wrap}.marko-debugbar-summary{order:2;flex:1 0 100%}.marko-debugbar-tabs{order:3;flex:1 0 100%;margin-left:0}.marko-debugbar-uri{display:none}}
@media (prefers-color-scheme:light){.marko-debugbar[data-theme=auto]{color:#17202e}.marko-debugbar[data-theme=auto] .marko-debugbar-bar{background:#f8fafc;border-top-color:#cbd5e1;box-shadow:0 -4px 14px rgba(15,23,42,.12)}.marko-debugbar[data-theme=auto] .marko-debugbar-brand{border-color:#bae6fd;background:#e0f2fe;color:#0369a1}.marko-debugbar[data-theme=auto] .marko-debugbar-uri{color:#334155}.marko-debugbar[data-theme=auto] .marko-debugbar-pill{border-color:#cbd5e1;background:#fff}.marko-debugbar[data-theme=auto] button:hover,.marko-debugbar[data-theme=auto] a:hover{background:#e2e8f0}.marko-debugbar[data-theme=auto] button[data-active=true]{background:#e2e8f0;color:#17202e}.marko-debugbar[data-theme=auto] .marko-debugbar-toggle{border-color:#cbd5e1!important;background:#fff!important}.marko-debugbar[data-theme=auto] .marko-debugbar-panel{background:#fff;border-top-color:#cbd5e1}.marko-debugbar[data-theme=auto] th,.marko-debugbar[data-theme=auto] td{border-bottom-color:#e2e8f0}}
</style>
<div class="marko-debugbar-bar">
  <div class="marko-debugbar-brand" title="Marko Debugbar">M</div>
  <div class="marko-debugbar-summary">
    <span class="marko-debugbar-pill">$method</span>
    <span class="marko-debugbar-pill">$duration</span>
    <span class="marko-debugbar-pill">$memory</span>
    <span class="marko-debugbar-pill">$messages messages</span>
    <span class="marko-debugbar-pill">$queries queries</span>
    <span class="marko-debugbar-pill">$logs logs</span>
    <span class="marko-debugbar-uri">$uri</span>
  </div>
  <div class="marko-debugbar-tabs" role="tablist">$tabs</div>
  <a class="marko-debugbar-open" href="$profilerUrl" target="_blank" rel="noreferrer">Open</a>
  <button type="button" class="marko-debugbar-toggle" data-marko-debugbar-toggle aria-expanded="false">Expand</button>
  <button type="button" class="marko-debugbar-close" data-marko-debugbar-close aria-label="Close debugbar">x</button>
</div>
<div class="marko-debugbar-panel" data-marko-debugbar-panel-wrap hidden>$panels</div>
<script>
(() => {
  const root = document.getElementById('marko-debugbar-$id');
  if (!root) return;
  const panelWrap = root.querySelector('[data-marko-debugbar-panel-wrap]');
  const toggle = root.querySelector('[data-marko-debugbar-toggle]');
  const setExpanded = (expanded) => {
    root.setAttribute('data-marko-debugbar-state', expanded ? 'expanded' : 'collapsed');
    if (panelWrap) panelWrap.hidden = !expanded;
    if (toggle) {
      toggle.textContent = expanded ? 'Collapse' : 'Expand';
      toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  };
  root.querySelectorAll('[data-marko-debugbar-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
      const name = tab.getAttribute('data-marko-debugbar-tab');
      root.querySelectorAll('[data-marko-debugbar-tab]').forEach((item) => {
        item.removeAttribute('data-active');
        item.setAttribute('aria-selected', 'false');
      });
      root.querySelectorAll('[data-marko-debugbar-panel]').forEach((panel) => {
        panel.hidden = panel.getAttribute('data-marko-debugbar-panel') !== name;
      });
      tab.setAttribute('data-active', 'true');
      tab.setAttribute('aria-selected', 'true');
      setExpanded(true);
    });
  });
  toggle?.addEventListener('click', () => setExpanded(root.getAttribute('data-marko-debugbar-state') !== 'expanded'));
  root.querySelector('[data-marko-debugbar-close]')?.addEventListener('click', () => root.remove());
})();
</script>
</div>
HTML;
    }

    /**
     * @param array<string, mixed> $collector
     */
    private function renderCollector(
        string $name,
        array $collector,
    ): string {
        return match ($name) {
            'messages' => $this->renderMessages($collector),
            'time' => $this->renderTime($collector),
            'memory' => $this->renderKeyValue($collector, ['label', 'badge']),
            'request' => $this->renderKeyValue($collector, ['label', 'badge']),
            'database' => $this->renderDatabase($collector),
            'logs' => $this->renderLogs($collector),
            'config' => $this->renderKeyValue($collector, ['label', 'badge']),
            default => $this->renderKeyValue($collector, ['label', 'badge']),
        };
    }

    /**
     * @param array<string, mixed> $collector
     */
    private function renderMessages(array $collector): string
    {
        $messages = is_array($collector['messages'] ?? null) ? $collector['messages'] : [];

        if ($messages === []) {
            return '<p>No messages collected.</p>';
        }

        $rows = '';

        foreach ($messages as $messageData) {
            $message = $this->stringKeyArray($messageData);

            if ($message === []) {
                continue;
            }

            $rows .= '<tr>'
                .'<td class="marko-debugbar-level">'.$this->escape(
                    $this->stringValue($message['level'] ?? null, 'info'),
                ).'</td>'
                .'<td>'.$this->escape($this->stringValue($message['time_ms'] ?? null, '0')).' ms</td>'
                .'<td>'.$this->escape($this->stringValue($message['message'] ?? null, '')).$this->renderContext(
                    $message,
                ).'</td>'
                .'</tr>';
        }

        return '<table><thead><tr><th>Level</th><th>Time</th><th>Message</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * @param array<string, mixed> $message
     */
    private function renderContext(array $message): string
    {
        $context = $message['context'] ?? [];
        $trace = $message['trace'] ?? null;
        $output = '';

        if (is_array($context) && $context !== []) {
            $output .= '<pre>'.$this->escape($this->json($context)).'</pre>';
        }

        if (is_array($trace)) {
            $output .= '<pre>'.$this->escape($this->stringValue($trace['file'] ?? null, '')).':'.$this->escape(
                $this->stringValue($trace['line'] ?? null, ''),
            ).'</pre>';
        }

        return $output;
    }

    /**
     * @param array<string, mixed> $collector
     */
    private function renderTime(array $collector): string
    {
        $measures = is_array($collector['measures'] ?? null) ? $collector['measures'] : [];

        $html = '<table><tbody>'
            .'<tr><th>Total</th><td>'.$this->escape(
                $this->stringValue($collector['duration_ms'] ?? null, '0'),
            ).' ms</td></tr>'
            .'</tbody></table>';

        if ($measures === []) {
            return $html.'<p>No custom measures collected.</p>';
        }

        $rows = '';

        foreach ($measures as $measureData) {
            $measure = $this->stringKeyArray($measureData);

            if ($measure === []) {
                continue;
            }

            $rows .= '<tr>'
                .'<td>'.$this->escape($this->stringValue($measure['name'] ?? null, '')).'</td>'
                .'<td>'.$this->escape($this->stringValue($measure['start_ms'] ?? null, '0')).' ms</td>'
                .'<td>'.$this->escape($this->stringValue($measure['duration_ms'] ?? null, '0')).' ms</td>'
                .'</tr>';
        }

        return $html.'<table><thead><tr><th>Measure</th><th>Start</th><th>Duration</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * @param array<string, mixed> $collector
     */
    private function renderDatabase(array $collector): string
    {
        $queries = is_array($collector['queries'] ?? null) ? $collector['queries'] : [];

        $html = '<table><tbody>'
            .'<tr><th>Queries</th><td>'.$this->escape(
                $this->stringValue($collector['count'] ?? null, '0'),
            ).'</td></tr>'
            .'<tr><th>Total</th><td>'.$this->escape(
                $this->stringValue($collector['duration_ms'] ?? null, '0'),
            ).' ms</td></tr>'
            .'</tbody></table>';

        if ($queries === []) {
            return $html.'<p>No database queries collected.</p>';
        }

        $threshold = $this->floatValue($collector['slow_threshold_ms'] ?? null, 100.0);
        $rows = '';

        foreach ($queries as $queryData) {
            $query = $this->stringKeyArray($queryData);

            if ($query === []) {
                continue;
            }

            $duration = $this->floatValue($query['duration_ms'] ?? null, 0.0);
            $slow = $duration >= $threshold ? ' class="marko-debugbar-level"' : '';
            $bindings = is_array($query['bindings'] ?? null) && $query['bindings'] !== []
                ? '<pre>'.$this->escape($this->json($query['bindings'])).'</pre>'
                : '';

            $rows .= '<tr>'
                .'<td>'.$this->escape($this->stringValue($query['type'] ?? null, '')).'</td>'
                ."<td$slow>".$this->escape($this->stringValue($query['duration_ms'] ?? null, '0')).' ms</td>'
                .'<td>'.$this->escape($this->stringValue($query['rows'] ?? null, '0')).'</td>'
                .'<td><pre>'.$this->escape($this->stringValue($query['sql'] ?? null, '')).'</pre>'.$bindings.'</td>'
                .'</tr>';
        }

        return $html.'<table><thead><tr><th>Type</th><th>Duration</th><th>Rows</th><th>SQL</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * @param array<string, mixed> $collector
     */
    private function renderLogs(array $collector): string
    {
        $logs = is_array($collector['logs'] ?? null) ? $collector['logs'] : [];

        if ($logs === []) {
            return '<p>No logs collected.</p>';
        }

        $rows = '';

        foreach ($logs as $logData) {
            $log = $this->stringKeyArray($logData);

            if ($log === []) {
                continue;
            }

            $context = is_array($log['context'] ?? null) && $log['context'] !== []
                ? '<pre>'.$this->escape($this->json($log['context'])).'</pre>'
                : '';

            $rows .= '<tr>'
                .'<td class="marko-debugbar-level">'.$this->escape(
                    $this->stringValue($log['level'] ?? null, 'info'),
                ).'</td>'
                .'<td>'.$this->escape($this->stringValue($log['time_ms'] ?? null, '0')).' ms</td>'
                .'<td>'.$this->escape($this->stringValue($log['message'] ?? null, '')).$context.'</td>'
                .'</tr>';
        }

        return '<table><thead><tr><th>Level</th><th>Time</th><th>Message</th></tr></thead><tbody>'.$rows.'</tbody></table>';
    }

    /**
     * @param array<string, mixed> $values
     * @param list<string> $skip
     */
    private function renderKeyValue(
        array $values,
        array $skip = [],
    ): string {
        $rows = '';

        foreach ($values as $key => $value) {
            if (in_array((string) $key, $skip, true)) {
                continue;
            }

            $rows .= '<tr><th>'.$this->escape($key).'</th><td><pre>'.$this->escape(
                $this->value($value),
            ).'</pre></td></tr>';
        }

        return '<table><tbody>'.$rows.'</tbody></table>';
    }

    private function value(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            return $this->json($value);
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return get_debug_type($value);
    }

    private function json(mixed $value): string
    {
        try {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '[unserializable]';
        }
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function stringValue(
        mixed $value,
        string $default,
    ): string {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return $default;
    }

    private function floatValue(
        mixed $value,
        float $default,
    ): float {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    /**
     * @return array<string, mixed>
     */
    private function stringKeyArray(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (! is_string($key)) {
                continue;
            }

            $result[$key] = $item;
        }

        return $result;
    }
}
