<?php

declare(strict_types=1);

use Marko\Lsp\Server\LspServer;

return [
    'bindings' => [],
    'singletons' => [
        LspServer::class,
    ],
];
