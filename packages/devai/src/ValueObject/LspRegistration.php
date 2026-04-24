<?php

declare(strict_types=1);

namespace Marko\DevAi\ValueObject;

readonly class LspRegistration
{
    /**
     * @param list<string> $args
     * @param list<string> $fileExtensions
     */
    public function __construct(
        public string $serverName,
        public string $command,
        public array $args,
        public array $fileExtensions = ['php', 'latte'],
    ) {}
}
