<?php

declare(strict_types=1);

use Marko\Validation\Contracts\MultiMessageRuleInterface;
use Marko\Validation\Validation\Validator;

function twoMessageRule(): MultiMessageRuleInterface
{
    return new class () implements MultiMessageRuleInterface
    {
        public function passes(
            string $field,
            mixed $value,
            array $data,
        ): bool {
            return false;
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
            return ["The $field field is too short.", "The $field field needs a number."];
        }
    };
}

it('adds each message of a failing multi-message rule as its own error', function (): void {
    $errors = new Validator()->validate(['password' => 'abc'], ['password' => [twoMessageRule()]]);

    expect($errors->get('password'))->toBe([
        'The password field is too short.',
        'The password field needs a number.',
    ]);
});
