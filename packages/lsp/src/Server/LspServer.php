<?php

declare(strict_types=1);

namespace Marko\Lsp\Server;

use Marko\Lsp\Features\AttributeFeature;
use Marko\Lsp\Features\CodeLensFeature;
use Marko\Lsp\Features\ConfigKeyFeature;
use Marko\Lsp\Features\TemplateFeature;
use Marko\Lsp\Features\TranslationFeature;
use Marko\Lsp\Protocol\LspProtocol;

class LspServer
{
    private const string SERVER_NAME = 'marko-lsp';

    private const string SERVER_VERSION = '1.0.0';

    private bool $initialized = false;

    private bool $shuttingDown = false;

    public function __construct(
        private LspProtocol $protocol,
        private DocumentStore $documents,
        private AttributeFeature $attributes,
        private CodeLensFeature $codeLens,
        private ConfigKeyFeature $configKeys,
        private TemplateFeature $templates,
        private TranslationFeature $translations,
    ) {
        $this->registerHandlers();
    }

    public function serve(): void
    {
        $this->protocol->serve();
    }

    private function registerHandlers(): void
    {
        $this->protocol->registerMethod('initialize', fn (array $params) => $this->initialize($params));
        $this->protocol->registerMethod('initialized', fn (array $params) => null);
        $this->protocol->registerMethod('shutdown', fn (array $params) => $this->shutdown());
        $this->protocol->registerMethod('textDocument/didOpen', fn (array $params) => $this->didOpen($params));
        $this->protocol->registerMethod('textDocument/didChange', fn (array $params) => $this->didChange($params));
        $this->protocol->registerMethod('textDocument/didClose', fn (array $params) => $this->didClose($params));
        $this->protocol->registerMethod('textDocument/completion', fn (array $params) => $this->completion($params));
        $this->protocol->registerMethod('textDocument/diagnostic', fn (array $params) => $this->diagnostic($params));
        $this->protocol->registerMethod(
            'textDocument/codeLens',
            fn (array $params) => $this->codeLensRequest($params)
        );
    }

    /** @param array<string, mixed> $params */
    public function initialize(array $params): array
    {
        $this->initialized = true;

        return [
            'capabilities' => [
                'textDocumentSync' => 1,
                'completionProvider' => [
                    'triggerCharacters' => ['"', "'", ':', '.'],
                    'resolveProvider' => false,
                ],
                'definitionProvider' => false,
                'hoverProvider' => false,
                'codeLensProvider' => [
                    'resolveProvider' => false,
                ],
                'diagnosticProvider' => [
                    'interFileDependencies' => false,
                    'workspaceDiagnostics' => false,
                ],
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ];
    }

    public function shutdown(): null
    {
        $this->shuttingDown = true;

        return null;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public function isShuttingDown(): bool
    {
        return $this->shuttingDown;
    }

    /** @param array<string, mixed> $params */
    private function didOpen(array $params): null
    {
        $uri = (string) ($params['textDocument']['uri'] ?? '');
        $text = (string) ($params['textDocument']['text'] ?? '');
        $this->documents->open($uri, $text);

        return null;
    }

    /** @param array<string, mixed> $params */
    private function didChange(array $params): null
    {
        $uri = (string) ($params['textDocument']['uri'] ?? '');
        $changes = $params['contentChanges'] ?? [];

        if (!is_array($changes)) {
            return null;
        }

        foreach ($changes as $change) {
            if (is_array($change) && !isset($change['range'])) {
                $this->documents->update($uri, (string) ($change['text'] ?? ''));
            }
        }

        return null;
    }

    /** @param array<string, mixed> $params */
    private function didClose(array $params): null
    {
        $uri = (string) ($params['textDocument']['uri'] ?? '');
        $this->documents->close($uri);

        return null;
    }

    /**
     * @param array<string, mixed> $params
     * @return array{isIncomplete: bool, items: list<array<string, mixed>>}
     */
    private function completion(array $params): array
    {
        $uri = (string) ($params['textDocument']['uri'] ?? '');
        $line = (int) ($params['position']['line'] ?? 0);
        $character = (int) ($params['position']['character'] ?? 0);
        $lineText = $this->documents->lineAt($uri, $line);

        $items = [];

        $configPartial = $this->configKeys->detectContext($lineText, $character);

        if ($configPartial !== null) {
            $items = array_merge($items, $this->configKeys->complete($configPartial));
        }

        $translationPartial = $this->translations->detectContext($lineText, $character);

        if ($translationPartial !== null) {
            $items = array_merge($items, $this->translations->complete($translationPartial));
        }

        $items = array_merge($items, $this->attributes->complete($lineText, $character));

        return ['isIncomplete' => false, 'items' => $items];
    }

    /**
     * @param array<string, mixed> $params
     * @return array{kind: string, items: list<array<string, mixed>>}
     */
    private function diagnostic(array $params): array
    {
        $uri = (string) ($params['textDocument']['uri'] ?? '');
        $text = $this->documents->get($uri) ?? '';

        return [
            'kind' => 'full',
            'items' => array_merge(
                $this->configKeys->diagnostics($text),
                $this->templates->diagnostics($text),
                $this->translations->diagnostics($text),
                $this->attributes->diagnostics($text),
            ),
        ];
    }

    /**
     * @param array<string, mixed> $params
     * @return list<array<string, mixed>>
     */
    private function codeLensRequest(array $params): array
    {
        $uri = (string) ($params['textDocument']['uri'] ?? '');
        $text = $this->documents->get($uri) ?? '';

        return $this->codeLens->lenses($text);
    }
}
