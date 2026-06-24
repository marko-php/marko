<?php

declare(strict_types=1);

use Marko\CodeIndexer\Cache\IndexCache;
use Marko\Core\Container\ContainerInterface;
use Marko\Core\Path\ProjectPaths;
use Marko\Docs\Contract\DocsSearchInterface;
use Marko\Mcp\Protocol\JsonRpcProtocol;
use Marko\Mcp\Server\McpServer;
use Marko\Mcp\Tools\CheckConfigKeyTool;
use Marko\Mcp\Tools\FindEventObserversTool;
use Marko\Mcp\Tools\FindPluginsTargetingTool;
use Marko\Mcp\Tools\GetConfigSchemaTool;
use Marko\Mcp\Tools\ListCommandsTool;
use Marko\Mcp\Tools\ListModulesTool;
use Marko\Mcp\Tools\ListRoutesTool;
use Marko\Mcp\Tools\ResolvePreferenceTool;
use Marko\Mcp\Tools\ResolveTemplateTool;
use Marko\Mcp\Tools\Runtime\Adapters\FileLogReader;
use Marko\Mcp\Tools\Runtime\Adapters\MarkoConsoleDispatcher;
use Marko\Mcp\Tools\Runtime\Adapters\MarkoQueryConnection;
use Marko\Mcp\Tools\Runtime\AppInfoTool;
use Marko\Mcp\Tools\Runtime\Contracts\LogReaderInterface;
use Marko\Mcp\Tools\Runtime\QueryDatabaseTool;
use Marko\Mcp\Tools\Runtime\ReadLogEntriesTool;
use Marko\Mcp\Tools\Runtime\RunConsoleCommandTool;
use Marko\Mcp\Tools\SearchDocsTool;
use Marko\Mcp\Tools\ValidateModuleTool;

return [
    'bindings' => [
        // LogReaderInterface is the one runtime seam worth keeping: a future
        // log driver (syslog, cloudwatch) can implement it. read_log_entries
        // reads through it; the default parses marko/log-file's on-disk format.
        LogReaderInterface::class => fn (ContainerInterface $c): LogReaderInterface => new FileLogReader(
            logsDir: $c->get(ProjectPaths::class)->base . '/storage/logs',
        ),
        McpServer::class => function (ContainerInterface $c): McpServer {
            $server = new McpServer($c->get(JsonRpcProtocol::class));
            $index = $c->get(IndexCache::class);
            $paths = $c->get(ProjectPaths::class);

            foreach ([
                CheckConfigKeyTool::class,
                FindEventObserversTool::class,
                FindPluginsTargetingTool::class,
                GetConfigSchemaTool::class,
                ListCommandsTool::class,
                ListModulesTool::class,
                ListRoutesTool::class,
                ResolvePreferenceTool::class,
                ResolveTemplateTool::class,
                ValidateModuleTool::class,
            ] as $tool) {
                $server->registerTool($tool::definition($index));
            }

            $server->registerTool(AppInfoTool::definition(
                composerJsonPath: $paths->base . '/composer.json',
                installedJsonPath: $paths->vendor . '/composer/installed.json',
            ));

            // "Most recent error" is served by read_log_entries(level: error);
            // there is no separate last_error tool (and no global error-capture
            // plugin running in production to feed one).
            $server->registerTool(ReadLogEntriesTool::definition(
                $c->get(LogReaderInterface::class),
            ));

            $server->registerTool(RunConsoleCommandTool::definition(
                $c->get(MarkoConsoleDispatcher::class),
            ));

            try {
                $server->registerTool(QueryDatabaseTool::definition(
                    $c->get(MarkoQueryConnection::class),
                ));
            } catch (Throwable) {
                // marko/database driver not installed — query_database tool unavailable
            }

            try {
                $server->registerTool(SearchDocsTool::definition(
                    $c->get(DocsSearchInterface::class),
                ));
            } catch (Throwable) {
                // No docs driver (docs-fts/etc.) installed — search_docs unavailable
            }

            return $server;
        },
    ],
    'singletons' => [],
];
