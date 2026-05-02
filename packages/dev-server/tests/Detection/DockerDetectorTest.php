<?php

declare(strict_types=1);

use Marko\DevServer\Detection\DockerDetector;

it('detects compose.yaml in project root', function (): void {
    $tmpDir = sys_get_temp_dir() . '/docker-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/compose.yaml', 'version: "3"');

    $detector = new DockerDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->not->toBeNull();

    unlink($tmpDir . '/compose.yaml');
    rmdir($tmpDir);
});

it('detects docker-compose.yml in project root', function (): void {
    $tmpDir = sys_get_temp_dir() . '/docker-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/docker-compose.yml', 'version: "3"');

    $detector = new DockerDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->not->toBeNull();

    unlink($tmpDir . '/docker-compose.yml');
    rmdir($tmpDir);
});

it('returns null when no compose file exists', function (): void {
    $tmpDir = sys_get_temp_dir() . '/docker-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);

    $detector = new DockerDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toBeNull();

    rmdir($tmpDir);
});

it('returns up command without detached flag', function (): void {
    $tmpDir = sys_get_temp_dir() . '/docker-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/compose.yaml', 'version: "3"');

    $detector = new DockerDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toHaveKey('upCommand')
        ->and($result['upCommand'])->not->toContain('-d');

    unlink($tmpDir . '/compose.yaml');
    rmdir($tmpDir);
});

it('returns down command for stopping containers', function (): void {
    $tmpDir = sys_get_temp_dir() . '/docker-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/compose.yaml', 'version: "3"');

    $detector = new DockerDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toHaveKey('downCommand')
        ->and($result['downCommand'])->toContain('down');

    unlink($tmpDir . '/compose.yaml');
    rmdir($tmpDir);
});

it('checks compose files in priority order', function (): void {
    $tmpDir = sys_get_temp_dir() . '/docker-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);

    // Create all compose files - compose.yaml should take priority
    file_put_contents($tmpDir . '/compose.yaml', 'version: "3"');
    file_put_contents($tmpDir . '/compose.yml', 'version: "3"');
    file_put_contents($tmpDir . '/docker-compose.yaml', 'version: "3"');
    file_put_contents($tmpDir . '/docker-compose.yml', 'version: "3"');

    $detector = new DockerDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toHaveKey('upCommand')
        ->and($result['upCommand'])->toContain('compose.yaml');

    unlink($tmpDir . '/compose.yaml');
    unlink($tmpDir . '/compose.yml');
    unlink($tmpDir . '/docker-compose.yaml');
    unlink($tmpDir . '/docker-compose.yml');
    rmdir($tmpDir);
});

it('uses compose file path in command with -f flag', function (): void {
    $tmpDir = sys_get_temp_dir() . '/docker-detector-test-' . bin2hex(random_bytes(8));
    mkdir($tmpDir, 0755, true);
    file_put_contents($tmpDir . '/docker-compose.yml', 'version: "3"');

    $detector = new DockerDetector($tmpDir);
    $result = $detector->detect();

    expect($result)->toHaveKey('upCommand')
        ->and($result['upCommand'])->toContain('-f docker-compose.yml')
        ->and($result['downCommand'])->toContain('-f docker-compose.yml');

    unlink($tmpDir . '/docker-compose.yml');
    rmdir($tmpDir);
});
