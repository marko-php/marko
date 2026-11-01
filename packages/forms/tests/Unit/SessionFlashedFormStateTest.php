<?php

declare(strict_types=1);

use Marko\Forms\SessionFlashedFormState;
use Marko\Forms\Tests\Fixtures\FormsHarness;
use Marko\Session\Flash\SessionFlashData;
use Marko\Testing\Fake\FakeSession;
use Marko\Validation\Contracts\FlashedFormStateInterface;
use Marko\Validation\Validation\ValidationErrors;

describe('SessionFlashedFormState', function (): void {
    it('implements FlashedFormStateInterface', function (): void {
        expect(new FormsHarness()->state)->toBeInstanceOf(FlashedFormStateInterface::class);
    });

    it('is empty when nothing was flashed', function (): void {
        $harness = new FormsHarness();

        expect($harness->state->errors())->toBeInstanceOf(ValidationErrors::class)
            ->and($harness->state->errors()->isEmpty())->toBeTrue()
            ->and($harness->state->hasErrors())->toBeFalse()
            ->and($harness->state->old('email'))->toBeNull()
            ->and($harness->state->old('email', 'fallback'))->toBe('fallback');
    });

    it('reads the flashed errors on the next request', function (): void {
        $harness = new FormsHarness();

        $harness->writer->flash(new ValidationErrors([
            'email' => ['Required.', 'Must be an email.'],
            'name' => ['Too short.'],
        ]), []);
        $harness->nextRequest();

        expect($harness->state->hasErrors())->toBeTrue()
            ->and($harness->state->errors()->first('email'))->toBe('Required.')
            ->and($harness->state->errors()->get('email'))->toBe(['Required.', 'Must be an email.'])
            ->and($harness->state->errors()->keys())->toBe(['email', 'name']);
    });

    it('reads old input by dot path', function (): void {
        $harness = new FormsHarness();

        $harness->writer->flash(new ValidationErrors(), [
            'address' => ['city' => 'Lisbon'],
            'items' => [['sku' => 'A-1']],
            'note' => null,
        ]);
        $harness->nextRequest();

        expect($harness->state->old('address.city'))->toBe('Lisbon')
            ->and($harness->state->old('items.0.sku'))->toBe('A-1')
            ->and($harness->state->old('address'))->toBe(['city' => 'Lisbon'])
            ->and($harness->state->old('address.zip', '0000'))->toBe('0000')
            ->and($harness->state->old('note', 'default'))->toBeNull();
    });

    it('does not consume the state when read', function (): void {
        $harness = new FormsHarness();

        $harness->writer->flash(new ValidationErrors(['email' => ['Required.']]), ['email' => 'x']);
        $harness->nextRequest();
        $harness->state->errors();
        $harness->state->old('email');

        expect($harness->state->hasErrors())->toBeTrue()
            ->and($harness->state->old('email'))->toBe('x');
    });

    it('is empty without reading or starting a session that was not started', function (): void {
        $session = new FakeSession();
        $session->arm();
        $state = new SessionFlashedFormState(new SessionFlashData($session), $session);

        expect($state->hasErrors())->toBeFalse()
            ->and($state->old('email', 'none'))->toBe('none')
            ->and($session->started)->toBeFalse();
    });
});
