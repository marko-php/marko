<?php

declare(strict_types=1);

namespace Marko\Lsp\Server;

use Marko\Lsp\Protocol\LspProtocol;

class DiagnosticsNotifier
{
    private const string SOURCE = 'marko-lsp';

    public function __construct(private LspProtocol $protocol) {}

    /**
     * Send a textDocument/publishDiagnostics notification, stamping every diagnostic
     * with source = "marko-lsp" as required by the LSP spec (clients use source to
     * distinguish diagnostic producers when multiple language servers are running).
     *
     * @param list<array<string, mixed>> $diagnostics
     */
    public function publish(string $uri, array $diagnostics): void
    {
        $stamped = array_map(
            fn (array $diag) => array_merge($diag, ['source' => self::SOURCE]),
            $diagnostics,
        );

        $this->protocol->notify('textDocument/publishDiagnostics', [
            'uri' => $uri,
            'diagnostics' => $stamped,
        ]);
    }
}
