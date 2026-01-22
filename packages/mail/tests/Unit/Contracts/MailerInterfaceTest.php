<?php

declare(strict_types=1);

use Marko\Mail\Contracts\MailerInterface;
use Marko\Mail\Exception\TransportException;
use Marko\Mail\Message;

test('MailerInterface defines send method', function (): void {
    $reflection = new ReflectionClass(MailerInterface::class);

    expect($reflection->isInterface())->toBeTrue();
    expect($reflection->hasMethod('send'))->toBeTrue();

    $method = $reflection->getMethod('send');

    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(1);
    expect($parameters[0]->getName())->toBe('message');
    expect($parameters[0]->getType()?->getName())->toBe(Message::class);

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull();
    expect($returnType->getName())->toBe('bool');

    // Verify @throws PHPDoc for TransportException
    $docComment = $method->getDocComment();
    expect($docComment)->toContain('@throws');
    expect($docComment)->toContain('TransportException');
});

test('MailerInterface defines sendRaw method', function (): void {
    $reflection = new ReflectionClass(MailerInterface::class);

    expect($reflection->hasMethod('sendRaw'))->toBeTrue();

    $method = $reflection->getMethod('sendRaw');

    expect($method->isPublic())->toBeTrue();

    $parameters = $method->getParameters();
    expect($parameters)->toHaveCount(2);
    expect($parameters[0]->getName())->toBe('to');
    expect($parameters[0]->getType()?->getName())->toBe('string');
    expect($parameters[1]->getName())->toBe('raw');
    expect($parameters[1]->getType()?->getName())->toBe('string');

    $returnType = $method->getReturnType();
    expect($returnType)->not->toBeNull();
    expect($returnType->getName())->toBe('bool');

    // Verify @throws PHPDoc for TransportException
    $docComment = $method->getDocComment();
    expect($docComment)->toContain('@throws');
    expect($docComment)->toContain('TransportException');
});
