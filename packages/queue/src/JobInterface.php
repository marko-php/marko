<?php

declare(strict_types=1);

namespace Marko\Queue;

interface JobInterface
{
    public function handle(): void;

    public function getId(): ?string;

    public function setId(string $id): void;

    public function getAttempts(): int;

    public function incrementAttempts(): void;

    public function getMaxAttempts(): int;

    public function serialize(): string;

    public static function unserialize(string $data): static;
}
