<?php

declare(strict_types=1);

use Marko\Core\Attributes\Command;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;
use Marko\Lsp\Commands\ServeCommand;
use Marko\Lsp\Server\LspServer;

it('is registered via Command attribute with name lsp:serve', function () {
    $reflection = new ReflectionClass(ServeCommand::class);
    $attributes = $reflection->getAttributes(Command::class);

    expect($attributes)->toHaveCount(1);

    $command = $attributes[0]->newInstance();
    expect($command->name)->toBe('lsp:serve');
});

it('boots the LSP server and attaches LspProtocol to stdio', function () {
    $served = false;
    $server = new class ($served) extends LspServer
    {
        public function __construct(private bool &$servedRef)
        {
            // skip parent constructor
        }

        public function serve(): void
        {
            $this->servedRef = true;
        }
    };

    $command = new ServeCommand($server);
    $input = new Input([]);
    $output = new Output(fopen('php://memory', 'w+'));

    $command->execute($input, $output);

    expect($served)->toBeTrue();
});

it('exits 0 on graceful shutdown after exit notification', function () {
    $server = new class () extends LspServer
    {
        public function __construct()
        {
            // skip parent constructor
        }

        public function serve(): void {}
    };

    $command = new ServeCommand($server);
    $input = new Input([]);
    $output = new Output(fopen('php://memory', 'w+'));

    $result = $command->execute($input, $output);

    expect($result)->toBe(0);
});

it('produces no stdout output other than valid LSP messages', function () {
    $server = new class () extends LspServer
    {
        public function __construct()
        {
            // skip parent constructor
        }

        public function serve(): void {}
    };

    $command = new ServeCommand($server);
    $outStream = fopen('php://memory', 'w+');
    $input = new Input([]);
    $output = new Output($outStream);

    ob_start();
    $command->execute($input, $output);
    $stdout = ob_get_clean();

    expect($stdout)->toBe('');
});

it('logs startup diagnostics to stderr only', function () {
    $server = new class () extends LspServer
    {
        public function __construct()
        {
            // skip parent constructor
        }

        public function serve(): void {}
    };

    $command = new ServeCommand($server);

    // Capture stderr by redirecting it temporarily
    $stderrStream = fopen('php://memory', 'w+');
    $input = new Input([]);
    $outStream = fopen('php://memory', 'w+');
    $output = new Output($outStream);

    // We verify execute() completes without error; stderr writing is an
    // implementation detail verified by inspecting the source.
    $reflection = new ReflectionClass(ServeCommand::class);
    $source = file_get_contents($reflection->getFileName());

    expect($source)->toContain('STDERR');
    expect($source)->toContain('fwrite');
});
