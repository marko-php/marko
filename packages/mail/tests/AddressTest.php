<?php

declare(strict_types=1);

use Marko\Mail\Address;
use Marko\Mail\Exceptions\MessageException;

describe('Address', function (): void {
    it('stores email correctly', function (): void {
        $address = new Address('john@example.com');

        expect($address->email)->toBe('john@example.com');
    });

    it('stores optional name correctly', function (): void {
        $address = new Address('john@example.com', 'John Doe');

        expect($address->name)->toBe('John Doe');
    });

    it('throws MessageException for invalid email', function (): void {
        expect(fn () => new Address('not-an-email'))
            ->toThrow(MessageException::class, "Invalid email address: 'not-an-email'");
    });

    it('toString formats with name correctly', function (): void {
        $address = new Address('john@example.com', 'John Doe');

        expect($address->toString())->toBe('John Doe <john@example.com>');
    });

    it('toString formats without name correctly', function (): void {
        $address = new Address('john@example.com');

        expect($address->toString())->toBe('john@example.com');
    });

    it('is readonly', function (): void {
        $reflection = new ReflectionClass(Address::class);

        expect($reflection->isReadOnly())->toBeTrue();
    });
});
