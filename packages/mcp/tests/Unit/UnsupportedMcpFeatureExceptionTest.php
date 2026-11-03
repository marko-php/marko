<?php

declare(strict_types=1);

use Marko\Core\Exceptions\UnsupportedFeatureExceptionInterface;
use Marko\Mcp\Exceptions\McpException;
use Marko\Mcp\Exceptions\UnsupportedMcpFeatureException;

describe('readOnlyUnsupportedDriver', function (): void {
    it(
        'makes the mcp read-only driver exception an McpException and an UnsupportedFeatureExceptionInterface',
        function (): void {
            $exception = McpException::readOnlyUnsupportedDriver('oracle');

            expect($exception)
                ->toBeInstanceOf(UnsupportedMcpFeatureException::class)
                ->toBeInstanceOf(McpException::class)
                ->toBeInstanceOf(UnsupportedFeatureExceptionInterface::class);
        },
    );

    it('keeps the internal error json-rpc code on the mcp read-only driver exception', function (): void {
        expect(McpException::readOnlyUnsupportedDriver('oracle')->getJsonRpcCode())->toBe(-32603);
    });

    it('reports the feature and driver of each capability exception', function (): void {
        $exception = McpException::readOnlyUnsupportedDriver('oracle');

        expect($exception->getFeature())->toBe('read-only queries')
            ->and($exception->getDriver())->toBe('oracle');
    });

    it('keeps the existing exception messages, contexts and suggestions', function (): void {
        $exception = McpException::readOnlyUnsupportedDriver('oracle');

        expect($exception->getMessage())->toBe(
            "query_database cannot run a read-only query on database driver 'oracle'",
        )
            ->and($exception->getContext())->toContain("so it refuses to run the query on 'oracle' at all.")
            ->and($exception->getSuggestion())->toBe(
                'Use a mysql, pgsql or sqlite connection for the MCP server, or add read-only transaction support for this driver to MarkoQueryConnection.',
            );
    });
});
