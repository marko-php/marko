<?php

declare(strict_types=1);

namespace Marko\Lsp\Commands;

use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Lsp\Server\LspServer;

#[Command(name: 'lsp:serve', description: 'Start the Marko LSP server on stdio')]
readonly class ServeCommand implements CommandInterface
{
    public function __construct(
        private LspServer $server,
    ) {}

    public function execute(
        Input $input,
        Output $output,
    ): int {
        fwrite(STDERR, "Marko LSP server starting on stdio...\n");
        $this->server->serve();
        fwrite(STDERR, "Marko LSP server shut down.\n");

        return 0;
    }
}
