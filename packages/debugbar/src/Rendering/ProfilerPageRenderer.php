<?php

declare(strict_types=1);

namespace Marko\Debugbar\Rendering;

use JsonException;

class ProfilerPageRenderer
{
    /**
     * @param list<array<string, mixed>> $items
     */
    public function index(array $items): string
    {
        $rows = '';
        $totalDuration = 0.0;
        $slowestDuration = 0.0;
        $slowestUri = 'n/a';
        $latestStoredAt = 'n/a';

        foreach ($items as $item) {
            $summary = $this->arrayValue($item['summary'] ?? null);
            $id = $this->stringValue($item['id'] ?? null, '');
            $url = $this->stringValue($item['profiler_url'] ?? null, '/_debugbar/'.$id);
            $method = $this->stringValue($summary['method'] ?? null, 'CLI');
            $uri = $this->stringValue($summary['uri'] ?? null, '/');
            $duration = $this->stringValue($summary['duration_ms'] ?? null, '0');
            $durationFloat = $this->floatValue($summary['duration_ms'] ?? null, 0.0);
            $storedAt = $this->stringValue($item['stored_at'] ?? null, '');
            $messages = $this->stringValue($summary['messages'] ?? null, '0');
            $queries = $this->stringValue($summary['queries'] ?? null, '0');
            $logs = $this->stringValue($summary['logs'] ?? null, '0');

            $totalDuration += $durationFloat;

            if ($durationFloat >= $slowestDuration) {
                $slowestDuration = $durationFloat;
                $slowestUri = $uri;
            }

            if ($latestStoredAt === 'n/a') {
                $latestStoredAt = $storedAt;
            }

            $rows .= '<tr>'
                .'<td><a class="request-id" href="'.$this->escape($url).'">'.$this->escape($id).'</a></td>'
                .'<td><span class="method method-'.$this->escape(strtolower($method)).'">'.$this->escape(
                    $method
                ).'</span></td>'
                .'<td><a class="uri" href="'.$this->escape($url).'">'.$this->escape($uri).'</a></td>'
                .'<td><span class="metric">'.$this->escape($duration).' ms</span></td>'
                .'<td><span class="subtle">'.$this->escape(
                    $messages
                ).' msg</span> <span class="subtle">'.$this->escape(
                    $queries
                ).' sql</span> <span class="subtle">'.$this->escape(
                    $logs
                ).' log</span></td>'
                .'<td>'.$this->escape($storedAt).'</td>'
                .'</tr>';
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="6" class="empty-state">No debugbar requests stored yet.</td></tr>';
        }

        $requestCount = count($items);
        $averageDuration = $requestCount > 0 ? round($totalDuration / $requestCount, 2) : 0;

        return $this->layout('Requests', <<<HTML
<header class="topbar">
  <div>
    <p>Marko Debugbar</p>
    <h1>Request Profiler</h1>
  </div>
  <div class="toolbar-actions">
    <span class="topbar-meta">Stored snapshots</span>
  </div>
</header>
<main>
  <div class="stats">
    <div><span>Requests</span><strong>{$this->escape((string) $requestCount)}</strong></div>
    <div><span>Average</span><strong>{$this->escape((string) $averageDuration)} ms</strong></div>
    <div><span>Slowest</span><strong>{$this->escape((string) $slowestDuration)} ms</strong><small>{$this->escape($slowestUri)}</small></div>
    <div><span>Latest</span><strong>{$this->escape($latestStoredAt)}</strong></div>
  </div>
  <section class="table-panel">
    <div class="section-title">
      <h2>Stored Requests</h2>
      <span>{$this->escape((string) $requestCount)} total</span>
    </div>
    <div class="table-wrap">
      <table>
        <thead><tr><th>ID</th><th>Method</th><th>URI</th><th>Duration</th><th>Activity</th><th>Stored</th></tr></thead>
        <tbody>$rows</tbody>
      </table>
    </div>
  </section>
</main>
HTML);
    }

    /**
     * @param array<string, mixed> $dataset
     */
    public function show(array $dataset): string
    {
        $id = $this->stringValue($dataset['id'] ?? null, '');
        $summary = $this->arrayValue($dataset['summary'] ?? null);
        $collectors = $this->arrayValue($dataset['collectors'] ?? null);
        $sections = '';
        $nav = '';

        foreach ($collectors as $name => $collector) {
            $collectorArray = $this->arrayValue($collector);
            $label = $this->stringValue($collectorArray['label'] ?? null, (string) $name);
            $badge = isset($collectorArray['badge'])
                ? '<span>'.$this->escape($this->stringValue($collectorArray['badge'], '')).'</span>'
                : '';
            $anchor = 'collector-'.$this->escape((string) $name);

            $nav .= '<a href="#'.$anchor.'">'.$this->escape($label).$badge.'</a>';
            $sections .= '<section id="'.$anchor.'" class="collector-card">'
                .'<div class="section-title"><h2>'.$this->escape($label).'</h2>'.$badge.'</div>'
                .$this->renderValue($collectorArray, ['label', 'badge'])
                .'</section>';
        }

        $method = $this->escape($this->stringValue($summary['method'] ?? null, 'CLI'));
        $uri = $this->escape($this->stringValue($summary['uri'] ?? null, '/'));
        $duration = $this->escape($this->stringValue($summary['duration_ms'] ?? null, '0'));
        $memory = $this->escape($this->stringValue($summary['memory'] ?? null, 'n/a'));
        $messages = $this->escape($this->stringValue($summary['messages'] ?? null, '0'));
        $queries = $this->escape($this->stringValue($summary['queries'] ?? null, '0'));
        $logs = $this->escape($this->stringValue($summary['logs'] ?? null, '0'));
        $views = $this->escape($this->stringValue($summary['views'] ?? null, '0'));

        return $this->layout($id, <<<HTML
<header class="topbar">
  <div>
    <p>Marko Debugbar</p>
    <h1><span class="method method-{$this->escape(strtolower($method))}">$method</span> $uri</h1>
  </div>
  <nav class="toolbar-actions">
    <a href="/_debugbar">All requests</a>
    <a href="/_debugbar/{$this->escape($id)}/json">JSON</a>
  </nav>
</header>
<main>
  <div class="stats">
    <div><span>Duration</span><strong>$duration ms</strong></div>
    <div><span>Memory</span><strong>$memory</strong></div>
    <div><span>Messages</span><strong>$messages</strong></div>
    <div><span>Queries</span><strong>$queries</strong></div>
    <div><span>Logs</span><strong>$logs</strong></div>
    <div><span>Views</span><strong>$views</strong></div>
  </div>
  <div class="detail-layout">
    <aside class="collector-nav">
      <strong>Collectors</strong>
      $nav
    </aside>
    <div>
      <section class="collector-card">
        <div class="section-title"><h2>Summary</h2><span>$id</span></div>
        {$this->renderValue($summary)}
      </section>
      $sections
    </div>
  </div>
</main>
HTML);
    }

    private function layout(
        string $title,
        string $body,
    ): string
    {
        $title = $this->escape($title);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>$title - Marko Debugbar</title>
  <style>
    :root{color-scheme:light dark;font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;color:#18202d;background:#f6f7f9;letter-spacing:0}
    *{box-sizing:border-box}
    body{margin:0}
    .topbar{display:flex;align-items:flex-end;justify-content:space-between;gap:24px;padding:26px 32px;background:#162131;color:#eef5ff;border-bottom:1px solid #2e3d52;box-shadow:0 12px 32px rgba(15,23,42,.14)}
    .topbar p{margin:0 0 6px;color:#7dd3fc;font-size:12px;font-weight:800;text-transform:uppercase}
    .topbar h1{display:flex;align-items:center;gap:10px;min-width:0;margin:0;font-size:24px;line-height:1.2;font-weight:750;letter-spacing:0;word-break:break-word}
    .toolbar-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
    .toolbar-actions a,.toolbar-actions button,.topbar-meta{display:inline-flex;align-items:center;min-height:32px;border:1px solid #3b4b60;border-radius:4px;padding:6px 10px;background:#101722;color:#bae6fd;text-decoration:none;font:inherit;cursor:pointer}
    .topbar-meta{cursor:default}
    main{max-width:1220px;margin:0 auto;padding:24px 18px 64px}
    .stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:10px;margin-bottom:18px}
    .stats div{min-width:0;padding:14px 15px;background:#fff;border:1px solid #d9e0e8;border-radius:8px}
    .stats span{display:block;margin-bottom:5px;color:#526174;font-size:12px;font-weight:700;text-transform:uppercase}
    .stats strong{display:block;color:#17202e;font-size:18px;line-height:1.2;word-break:break-word}
    .stats small{display:block;margin-top:5px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .table-panel,.collector-card{margin:0 0 18px;padding:0;background:#fff;border:1px solid #d9e0e8;border-radius:8px;overflow:hidden}
    .section-title{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 15px;border-bottom:1px solid #e2e8f0;background:#f8fafc}
    .section-title h2{margin:0;font-size:14px;line-height:1.25}
    .section-title span{display:inline-flex;align-items:center;min-height:22px;border:1px solid #cbd5e1;border-radius:4px;padding:2px 7px;color:#334155;background:#fff;font-size:12px}
    .table-wrap{overflow:auto}
    table{width:100%;border-collapse:collapse;background:#fff}
    th,td{text-align:left;vertical-align:top;border-bottom:1px solid #e2e8f0;padding:9px 10px}
    th{font-size:11px;text-transform:uppercase;color:#526174;white-space:nowrap}
    tr:last-child td{border-bottom:0}
    a{color:#0369a1}
    .request-id{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace;font-size:12px}
    .uri{display:inline-block;max-width:520px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .method,.metric,.subtle{display:inline-flex;align-items:center;min-height:22px;border-radius:4px;padding:2px 7px;white-space:nowrap}
    .method{background:#e0f2fe;color:#0369a1;border:1px solid #bae6fd;font-size:12px;font-weight:800}
    .metric{background:#f1f5f9;color:#17202e;border:1px solid #cbd5e1}
    .subtle{margin-right:4px;background:#f8fafc;color:#526174;border:1px solid #e2e8f0;font-size:12px}
    .empty-state{padding:42px 18px!important;text-align:center;color:#64748b}
    .detail-layout{display:grid;grid-template-columns:220px minmax(0,1fr);gap:18px;align-items:start}
    .collector-nav{position:sticky;top:16px;display:flex;flex-direction:column;gap:4px;padding:12px;background:#fff;border:1px solid #d9e0e8;border-radius:8px}
    .collector-nav strong{margin:0 0 6px;color:#526174;font-size:12px;text-transform:uppercase}
    .collector-nav a{display:flex;align-items:center;justify-content:space-between;gap:8px;border-radius:4px;padding:7px 8px;color:#17202e;text-decoration:none}
    .collector-nav a:hover{background:#eef6fb}
    .collector-nav span{color:#0369a1;font-size:12px}
    .collector-card>table{border-top:0}
    pre{margin:0;white-space:pre-wrap;word-break:break-word;font:12px/1.45 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace}
    @media (max-width:760px){.topbar{align-items:flex-start;flex-direction:column;padding:20px}.topbar h1{font-size:20px}.detail-layout{grid-template-columns:1fr}.collector-nav{position:static}.uri{max-width:280px}}
    @media (prefers-color-scheme:dark){:root{color:#dbe4ef;background:#0b1018}.topbar{background:#101722}.toolbar-actions a,.toolbar-actions button,.topbar-meta{background:#162131;border-color:#3b4b60;color:#7dd3fc}.stats div,.table-panel,.collector-card,.collector-nav,table{background:#111827;border-color:#2f3d4f}.stats span,.collector-nav strong{color:#93a8bf}.stats strong,.collector-nav a{color:#dbe4ef}.stats small,.empty-state{color:#93a8bf}.section-title{background:#101722;border-bottom-color:#2f3d4f}.section-title span,.metric,.subtle{background:#162131;border-color:#3b4b60;color:#dbe4ef}th,td{border-bottom-color:#2a3749}th{color:#93a8bf}a,.collector-nav span{color:#7dd3fc}.collector-nav a:hover{background:#162131}.method{background:#0b2230;border-color:#265b73;color:#7dd3fc}}
  </style>
</head>
<body>$body</body>
</html>
HTML;
    }

    /**
     * @param list<string> $skip
     */
    private function renderValue(
        mixed $value,
        array $skip = [],
    ): string
    {
        if (is_array($value)) {
            $rows = '';

            foreach ($value as $key => $item) {
                if (in_array((string) $key, $skip, true)) {
                    continue;
                }

                $rows .= '<tr><th>'.$this->escape((string) $key).'</th><td>'.$this->renderValue($item).'</td></tr>';
            }

            return '<table><tbody>'.$rows.'</tbody></table>';
        }

        return '<pre>'.$this->escape($this->scalarValue($value)).'</pre>';
    }

    /**
     * @return array<string, mixed>
     */
    private function arrayValue(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }

    private function scalarValue(mixed $value): string
    {
        if (is_array($value) || is_object($value)) {
            try {
                return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return '[unserializable]';
            }
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return 'null';
        }

        if (is_string($value) || is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return get_debug_type($value);
    }

    private function stringValue(
        mixed $value,
        string $default,
    ): string
    {
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
    ): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return $default;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
