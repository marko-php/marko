<?php

declare(strict_types=1);

namespace Marko\DevServer\Detection;

readonly class DockerDetector
{
    private const array COMPOSE_FILES = ['compose.yaml', 'compose.yml', 'docker-compose.yaml', 'docker-compose.yml'];

    public function __construct(
        private string $projectRoot,
    ) {}

    /**
     * @return array{upCommand: string, downCommand: string}|null
     */
    public function detect(): ?array
    {
        $found = array_filter(
            self::COMPOSE_FILES,
            fn (string $filename) => file_exists($this->projectRoot . '/' . $filename),
        );

        if ($found === []) {
            return null;
        }

        $composeFile = array_first($found);

        $binary = 'docker compose';

        return [
            'upCommand' => "$binary -f $composeFile up",
            'downCommand' => "$binary -f $composeFile down",
        ];
    }
}
