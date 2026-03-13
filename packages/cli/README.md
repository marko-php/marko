# marko/cli

Global command-line tool --- run `marko` from any project directory to execute commands.

## Installation

```bash
composer global require marko/cli
```

## Quick Example

```php
use Marko\Core\Attributes\Command;
use Marko\Core\Command\CommandInterface;
use Marko\Core\Command\Input;
use Marko\Core\Command\Output;

#[Command(name: 'greet', description: 'Say hello')]
class GreetCommand implements CommandInterface
{
    public function execute(Input $input, Output $output): int
    {
        $output->writeLine('Hello, World!');

        return 0;
    }
}
```

## Documentation

Full usage, API reference, and examples: [marko/cli](https://marko.build/docs/packages/cli/)
