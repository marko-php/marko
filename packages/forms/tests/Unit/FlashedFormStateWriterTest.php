<?php

declare(strict_types=1);

use Marko\Forms\Tests\Fixtures\FormsHarness;
use Marko\Session\Exceptions\InvalidFlashDataException;
use Marko\Validation\Validation\ValidationErrors;

describe('FlashedFormStateWriter', function (): void {
    it('flashes nothing readable on the current request', function (): void {
        $harness = new FormsHarness();

        $harness->writer->flash(new ValidationErrors(['email' => ['Required.']]), ['email' => 'x']);

        expect($harness->state->hasErrors())->toBeFalse();
    });

    it('removes nested never_flash fields given as dot paths', function (): void {
        $harness = new FormsHarness(['forms.never_flash' => ['user.password', 'card.number']]);

        $harness->writer->flash(new ValidationErrors(), [
            'user' => ['name' => 'Ann', 'password' => 'hunter2'],
            'card' => 'not-an-array',
            'password' => 'top-level-is-not-listed',
        ]);
        $harness->nextRequest();

        expect($harness->state->old('user'))->toBe(['name' => 'Ann'])
            ->and($harness->state->old('card'))->toBe('not-an-array')
            ->and($harness->state->old('password'))->toBe('top-level-is-not-listed');
    });

    it('always removes the CSRF token even when never_flash is empty', function (): void {
        $harness = new FormsHarness(['forms.never_flash' => []]);

        $harness->writer->flash(new ValidationErrors(), ['_token' => 't', 'password' => 'p']);
        $harness->nextRequest();

        expect($harness->state->old('_token'))->toBeNull()
            ->and($harness->state->old('password'))->toBe('p');
    });

    it('throws loudly for input that cannot be flashed', function (): void {
        $harness = new FormsHarness();

        $harness->writer->flash(new ValidationErrors(), ['when' => new DateTimeImmutable()]);
    })->throws(InvalidFlashDataException::class, "Cannot flash 'forms.old_input.when'");
});
