<?php

declare(strict_types=1);

use Marko\Queue\Rabbitmq\Exchange\ExchangeConfig;
use Marko\Queue\Rabbitmq\Exchange\ExchangeType;

it('creates ExchangeConfig with required name and type', function (): void {
    $config = new ExchangeConfig(
        name: 'test-exchange',
        type: ExchangeType::Direct,
    );

    expect($config->name)->toBe('test-exchange')
        ->and($config->type)->toBe(ExchangeType::Direct);
});

it('creates ExchangeConfig with all options including arguments', function (): void {
    $arguments = ['x-match' => 'all'];

    $config = new ExchangeConfig(
        name: 'headers-exchange',
        type: ExchangeType::Headers,
        durable: false,
        autoDelete: true,
        arguments: $arguments,
    );

    expect($config->name)->toBe('headers-exchange')
        ->and($config->type)->toBe(ExchangeType::Headers)
        ->and($config->durable)->toBeFalse()
        ->and($config->autoDelete)->toBeTrue()
        ->and($config->arguments)->toBe(['x-match' => 'all']);
});

it('defaults to durable non-auto-delete exchange', function (): void {
    $config = new ExchangeConfig(
        name: 'default-exchange',
        type: ExchangeType::Fanout,
    );

    expect($config->durable)->toBeTrue()
        ->and($config->autoDelete)->toBeFalse()
        ->and($config->arguments)->toBe([]);
});
