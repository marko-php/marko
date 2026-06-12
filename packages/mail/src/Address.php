<?php

declare(strict_types=1);

namespace Marko\Mail;

use Marko\Mail\Exceptions\MessageException;

readonly class Address
{
    /**
     * @throws MessageException
     */
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw MessageException::invalidEmailAddress($email);
        }

        if ($name !== null && (str_contains($name, "\r") || str_contains($name, "\n"))) {
            throw MessageException::headerInjection('display name', $name);
        }
    }

    public function toString(): string
    {
        if ($this->name === null) {
            return $this->email;
        }

        return sprintf('%s <%s>', $this->name, $this->email);
    }
}
