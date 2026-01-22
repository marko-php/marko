<?php

declare(strict_types=1);

namespace Marko\Mail;

use Marko\Mail\Exceptions\MessageException;

readonly class Address
{
    public function __construct(
        public string $email,
        public ?string $name = null,
    ) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw MessageException::invalidEmailAddress($email);
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
