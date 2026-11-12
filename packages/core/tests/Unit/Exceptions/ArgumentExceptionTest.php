<?php

declare(strict_types=1);

use Marko\Core\Exceptions\ArgumentException;
use Marko\Core\Exceptions\MarkoException;

it('names the class, the unknown parameter and the valid parameter names', function (): void {
    $exception = ArgumentException::unknownParameter('App\Mailer', 'hots', ['host', 'port']);

    expect($exception)
        ->toBeInstanceOf(MarkoException::class)
        ->and($exception->getMessage())
        ->toContain('App\Mailer')
        ->toContain('hots')
        ->and($exception->getSuggestion())
        ->toContain('host')
        ->toContain('port');
});

it('tells the reader to key arguments by the concrete class when given an interface', function (): void {
    $exception = ArgumentException::interfaceKey('App\MailerInterface');

    expect($exception->getMessage())
        ->toContain('App\MailerInterface')
        ->and($exception->getSuggestion())
        ->toContain('concrete class');
});

it('explains that a closure-bound class never receives arguments', function (): void {
    $exception = ArgumentException::closureBound('App\Mailer', ['app/mail (priority 10)']);

    expect($exception->getMessage())
        ->toContain('App\Mailer')
        ->toContain('closure')
        ->toContain('app/mail (priority 10)')
        ->and($exception->getSuggestion())
        ->toContain('higher-priority module');
});

it('asks for a closure factory when the parameter type is ambiguous', function (): void {
    $exception = ArgumentException::ambiguousType('App\Mailer', 'transport', 'int|string');

    expect($exception->getMessage())
        ->toContain('App\Mailer')
        ->toContain('transport')
        ->toContain('int|string')
        ->and($exception->getSuggestion())
        ->toContain('closure factory');
});

it('reports the expected and actual types on a literal type mismatch', function (): void {
    $exception = ArgumentException::typeMismatch('App\Mailer', 'port', 'int', 'string');

    expect($exception->getMessage())
        ->toContain('App\Mailer')
        ->toContain('port')
        ->toContain('int')
        ->toContain('string')
        ->and($exception->getSuggestion())
        ->toContain('int');
});

it('reports the class that does not satisfy the parameter type', function (): void {
    $exception = ArgumentException::classDoesNotSatisfyType(
        'App\Mailer',
        'transport',
        'App\TransportInterface',
        'App\Other',
    );

    expect($exception->getMessage())
        ->toContain('App\Mailer')
        ->toContain('transport')
        ->toContain('App\TransportInterface')
        ->toContain('App\Other')
        ->and($exception->getSuggestion())
        ->toContain('App\TransportInterface');
});

it('names the replaced class when an inherited argument has no matching parameter', function (): void {
    $exception = ArgumentException::unknownInheritedParameter(
        'App\FastMailer',
        'hots',
        'App\Mailer',
        ['host', 'port'],
    );

    expect($exception->getMessage())
        ->toContain('App\FastMailer')
        ->toContain('hots')
        ->toContain('App\Mailer')
        ->and($exception->getSuggestion())
        ->toContain('host')
        ->toContain('port');
});

it('explains that arguments on a class rebound to another class never apply', function (): void {
    $exception = ArgumentException::reboundClass('App\Mailer', 'App\SmtpMailer');

    expect($exception->getMessage())
        ->toContain('App\Mailer')
        ->toContain('App\SmtpMailer')
        ->and($exception->getSuggestion())
        ->toContain("Key the arguments by 'App\SmtpMailer'");
});
