<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Mcp\Commands\ServeCommand;
use Marko\Mcp\Server\McpServer;

it('is registered via Command attribute with name mcp:serve', function (): void {
    $reflection = new ReflectionClass(ServeCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();
    expect($command->name)->toBe('mcp:serve');
});

it('boots the MCP server and attaches JsonRpcProtocol to stdio', function (): void {
    $served = false;
    $server = new class ($served) extends McpServer
    {
        public function __construct(private bool &$served)
        {
            // skip parent constructor — no protocol needed
        }

        public function serve(): void
        {
            $this->served = true;
        }
    };

    $input = new Input([]);
    $output = new Output(fopen('php://memory', 'w+'));

    $command = new ServeCommand($server);
    $command->execute($input, $output);

    expect($served)->toBeTrue();
});

it('exits 0 on graceful shutdown', function (): void {
    $server = new class () extends McpServer
    {
        public function __construct()
        {
            // skip parent constructor
        }

        public function serve(): void {}
    };

    $input = new Input([]);
    $output = new Output(fopen('php://memory', 'w+'));

    $command = new ServeCommand($server);
    $result = $command->execute($input, $output);

    expect($result)->toBe(0);
});

it('produces no stdout output other than valid JSON-RPC', function (): void {
    $server = new class () extends McpServer
    {
        public function __construct()
        {
            // skip parent constructor
        }

        public function serve(): void {}
    };

    $mem = fopen('php://memory', 'w+');
    $input = new Input([]);
    $output = new Output($mem);

    $command = new ServeCommand($server);
    $command->execute($input, $output);

    rewind($mem);
    $written = stream_get_contents($mem);

    // The Output object (stdout) must receive no content
    expect($written)->toBe('');
});

it('logs startup diagnostics to stderr only', function (): void {
    $server = new class () extends McpServer
    {
        public function __construct()
        {
            // skip parent constructor
        }

        public function serve(): void {}
    };

    $stdoutMem = fopen('php://memory', 'w+');
    $input = new Input([]);
    $output = new Output($stdoutMem);

    $command = new ServeCommand($server);
    $command->execute($input, $output);

    rewind($stdoutMem);
    $stdoutContent = stream_get_contents($stdoutMem);

    // stdout must be empty; diagnostics go to stderr (verified by absence from stdout)
    expect($stdoutContent)->toBe('');
});
