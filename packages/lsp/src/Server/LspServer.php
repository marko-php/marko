<?php

declare(strict_types=1);

namespace Marko\Lsp\Server;

use Marko\Lsp\Protocol\LspProtocol;

class LspServer
{
    private const string SERVER_NAME = 'marko-lsp';

    private const string SERVER_VERSION = '1.0.0';

    private bool $initialized = false;

    private bool $shuttingDown = false;

    public function __construct(
        private LspProtocol $protocol,
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
                'definitionProvider' => true,
                'hoverProvider' => true,
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
}
