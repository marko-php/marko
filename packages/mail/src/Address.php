<?php

declare(strict_types=1);

namespace Marko\Mail;

use Marko\Mail\Exceptions\MessageException;

readonly class Address
{
    /**
     * RFC 5322 atext, plus bytes 0x80-0xFF as RFC 6532 UTF-8 atext.
     */
    private const string ATEXT = 'A-Za-z0-9!#$%&\'*+\/=?^_`{|}~\-\x80-\xFF';

    /**
     * A display name made only of atext words separated by single spaces needs no quoting.
     */
    private const string PHRASE_PATTERN = '/^[' . self::ATEXT . ']+(?: [' . self::ATEXT . ']+)*$/';

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

        if (
            $name !== null
            && (
                str_contains($name, "\r")
                || str_contains($name, "\n")
            )
        ) {
            throw MessageException::headerInjection('display name', $name);
        }
    }

    public function toString(): string
    {
        if ($this->name === null) {
            return $this->email;
        }

        return sprintf('%s <%s>', $this->formatDisplayName(), $this->email);
    }

    /**
     * Render the display name as an RFC 5322 phrase.
     *
     * A name that is a plain run of atext words is returned as is. Anything else (specials such as
     * `"`, `,`, `<`, `>`, `;`, `@`, `.`, extra whitespace or an empty name) is wrapped in a
     * quoted-string with `\` and `"` escaped, so a name like `x <attacker@evil.com>, y` stays one
     * display name instead of becoming extra addresses.
     */
    public function formatDisplayName(): string
    {
        $name = $this->name ?? '';

        if (preg_match(self::PHRASE_PATTERN, $name)) {
            return $name;
        }

        return '"' . addcslashes($name, '"\\') . '"';
    }
}
