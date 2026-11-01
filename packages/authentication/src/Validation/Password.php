<?php

declare(strict_types=1);

namespace Marko\Authentication\Validation;

use InvalidArgumentException;
use Marko\Validation\Contracts\MultiMessageRuleInterface;

/**
 * Password strength rule. Each builder method returns a new rule, so tightening the
 * configured defaults from AuthRules::password() for one form never changes them.
 * Every failed requirement is reported as its own error.
 */
readonly class Password implements MultiMessageRuleInterface
{
    /**
     * @throws InvalidArgumentException When $min is below 1
     */
    public function __construct(
        private int $min,
        private bool $mixedCase,
        private bool $letters,
        private bool $numbers,
        private bool $symbols,
    ) {
        if ($min < 1) {
            throw new InvalidArgumentException("Password minimum length must be at least 1, got $min");
        }
    }

    /**
     * Require at least this many characters, replacing the current minimum.
     *
     * @throws InvalidArgumentException When $min is below 1
     */
    public function min(
        int $min,
    ): self {
        return new self($min, $this->mixedCase, $this->letters, $this->numbers, $this->symbols);
    }

    /**
     * Require at least one uppercase and one lowercase letter.
     */
    public function mixedCase(): self
    {
        return new self($this->min, true, $this->letters, $this->numbers, $this->symbols);
    }

    /**
     * Require at least one letter.
     */
    public function letters(): self
    {
        return new self($this->min, $this->mixedCase, true, $this->numbers, $this->symbols);
    }

    /**
     * Require at least one number.
     */
    public function numbers(): self
    {
        return new self($this->min, $this->mixedCase, $this->letters, true, $this->symbols);
    }

    /**
     * Require at least one symbol (punctuation, a symbol or a space character).
     */
    public function symbols(): self
    {
        return new self($this->min, $this->mixedCase, $this->letters, $this->numbers, true);
    }

    public function passes(
        string $field,
        mixed $value,
        array $data,
    ): bool {
        return $this->messages($field, $value) === [];
    }

    public function message(
        string $field,
        mixed $value,
    ): string {
        return implode(' ', $this->messages($field, $value));
    }

    public function messages(
        string $field,
        mixed $value,
    ): array {
        if (!is_string($value)) {
            return ["The $field field must be a string."];
        }

        if (!mb_check_encoding($value, 'UTF-8')) {
            return ["The $field field must be valid UTF-8 text."];
        }

        $messages = [];

        if (mb_strlen($value, 'UTF-8') < $this->min) {
            $messages[] = "The $field field must be at least $this->min characters.";
        }

        if ($this->mixedCase && !($this->matches('/\p{Lu}/u', $value) && $this->matches('/\p{Ll}/u', $value))) {
            $messages[] = "The $field field must contain at least one uppercase and one lowercase letter.";
        }

        if ($this->letters && !$this->matches('/\p{L}/u', $value)) {
            $messages[] = "The $field field must contain at least one letter.";
        }

        if ($this->numbers && !$this->matches('/\p{N}/u', $value)) {
            $messages[] = "The $field field must contain at least one number.";
        }

        if ($this->symbols && !$this->matches('/[\p{Z}\p{S}\p{P}]/u', $value)) {
            $messages[] = "The $field field must contain at least one symbol.";
        }

        return $messages;
    }

    private function matches(
        string $pattern,
        string $value,
    ): bool {
        return preg_match($pattern, $value) === 1;
    }
}
