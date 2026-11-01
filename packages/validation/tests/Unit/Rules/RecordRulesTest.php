<?php

declare(strict_types=1);

use Marko\Validation\Rules\Exists;
use Marko\Validation\Rules\RecordRules;
use Marko\Validation\Rules\Unique;
use Marko\Validation\Tests\Support\FakeRecordLookup;
use Marko\Validation\Validation\Validator;

const RECORD_CHANNEL = 'App\Entity\Channel';
const RECORD_USER = 'App\Entity\User';

function recordLookup(): FakeRecordLookup
{
    return new FakeRecordLookup([
        RECORD_CHANNEL => [
            ['id' => 1, 'slug' => 'news', 'archived' => false],
            ['id' => 2, 'slug' => 'old', 'archived' => true],
        ],
        RECORD_USER => [
            ['id' => 10, 'email' => 'Ada@example.com', 'tenantId' => 1],
            ['id' => 11, 'email' => 'grace@example.com', 'tenantId' => 2],
        ],
    ]);
}

describe('RecordRules', function (): void {
    it('builds an Exists rule on the id property by default', function (): void {
        $lookup = recordLookup();
        $rule = new RecordRules($lookup)->exists(RECORD_CHANNEL);

        expect($rule)->toBeInstanceOf(Exists::class)
            ->and($rule->passes('channel_id', 1, []))->toBeTrue()
            ->and($lookup->calls[0])->toMatchArray([
                'method' => 'exists',
                'entityClass' => RECORD_CHANNEL,
                'property' => 'id',
                'value' => 1,
                'where' => [],
            ]);
    });

    it('builds a Unique rule on the given property', function (): void {
        $rule = new RecordRules(recordLookup())->unique(RECORD_USER, 'email');

        expect($rule)->toBeInstanceOf(Unique::class)
            ->and($rule->passes('email', 'new@example.com', []))->toBeTrue()
            ->and($rule->passes('email', 'grace@example.com', []))->toBeFalse();
    });
});

describe('Exists', function (): void {
    it('passes for an existing record and fails for a missing one', function (): void {
        $rule = new RecordRules(recordLookup())->exists(RECORD_CHANNEL);

        expect($rule->passes('channel_id', 1, []))->toBeTrue()
            ->and($rule->passes('channel_id', 99, []))->toBeFalse()
            ->and($rule->message('channel_id', 99))->toBe('The selected channel_id is invalid.');
    });

    it('looks up a property other than id', function (): void {
        $rule = new RecordRules(recordLookup())->exists(RECORD_CHANNEL, 'slug');

        expect($rule->passes('channel', 'news', []))->toBeTrue()
            ->and($rule->passes('channel', 'sport', []))->toBeFalse();
    });

    it('honours where() and leaves the original rule unchanged', function (): void {
        $lookup = recordLookup();
        $rule = new RecordRules($lookup)->exists(RECORD_CHANNEL);
        $active = $rule->where('archived', false);

        expect($active)->not->toBe($rule)
            ->and($active->passes('channel_id', 2, []))->toBeFalse()
            ->and($active->passes('channel_id', 1, []))->toBeTrue()
            ->and($rule->passes('channel_id', 2, []))->toBeTrue()
            ->and($lookup->calls[0]['where'])->toBe(['archived' => false])
            ->and($lookup->calls[2]['where'])->toBe([]);
    });

    it('skips the lookup for an empty value', function (mixed $value): void {
        $lookup = recordLookup();
        $rule = new RecordRules($lookup)->exists(RECORD_CHANNEL);

        expect($rule->passes('channel_id', $value, []))->toBeTrue()
            ->and($lookup->calls)->toBe([]);
    })->with(['null' => [null], 'empty string' => ['']]);

    it('fails a value that is not a scalar without a lookup', function (): void {
        $lookup = recordLookup();
        $rule = new RecordRules($lookup)->exists(RECORD_CHANNEL);

        expect($rule->passes('channel_id', [1], []))->toBeFalse()
            ->and($lookup->calls)->toBe([]);
    });
});

describe('Unique', function (): void {
    it('fails for a taken value and passes for a free one', function (): void {
        $rule = new RecordRules(recordLookup())->unique(RECORD_USER, 'email');

        expect($rule->passes('email', 'grace@example.com', []))->toBeFalse()
            ->and($rule->passes('email', 'free@example.com', []))->toBeTrue()
            ->and($rule->message('email', 'grace@example.com'))->toBe('The email has already been taken.');
    });

    it('passes the record itself with ignore()', function (): void {
        $lookup = recordLookup();
        $rule = new RecordRules($lookup)->unique(RECORD_USER, 'email');
        $ignoring = $rule->ignore(11);

        expect($ignoring)->not->toBe($rule)
            ->and($ignoring->passes('email', 'grace@example.com', []))->toBeTrue()
            ->and($rule->passes('email', 'grace@example.com', []))->toBeFalse()
            ->and($lookup->calls[0]['ignoreId'])->toBe(11)
            ->and($lookup->calls[1]['ignoreId'])->toBeNull();
    });

    it('honours where()', function (): void {
        $rules = new RecordRules(recordLookup());

        expect($rules->unique(RECORD_USER, 'email')->where('tenantId', 1)->passes('email', 'grace@example.com', []))
            ->toBeTrue()
            ->and($rules->unique(RECORD_USER, 'email')->where('tenantId', 2)->passes('email', 'grace@example.com', []))
            ->toBeFalse();
    });

    it('asks for a case-insensitive comparison with caseInsensitive()', function (): void {
        $lookup = recordLookup();
        $rule = new RecordRules($lookup)->unique(RECORD_USER, 'email');
        $insensitive = $rule->caseInsensitive();

        expect($rule->passes('email', 'ada@example.com', []))->toBeTrue()
            ->and($insensitive->passes('email', 'ada@example.com', []))->toBeFalse()
            ->and($lookup->calls[0]['caseInsensitive'])->toBeFalse()
            ->and($lookup->calls[1]['caseInsensitive'])->toBeTrue();
    });

    it('keeps every option when combined', function (): void {
        $lookup = recordLookup();
        $rule = new RecordRules($lookup)->unique(RECORD_USER, 'email')
            ->caseInsensitive()
            ->where('tenantId', 1)
            ->ignore(10);

        $rule->passes('email', 'ADA@example.com', []);

        expect($lookup->calls[0])->toBe([
            'method' => 'isTaken',
            'entityClass' => RECORD_USER,
            'property' => 'email',
            'value' => 'ADA@example.com',
            'ignoreId' => 10,
            'where' => ['tenantId' => 1],
            'caseInsensitive' => true,
        ]);
    });

    it('skips the lookup for an empty value and fails a non-scalar', function (): void {
        $lookup = recordLookup();
        $rule = new RecordRules($lookup)->unique(RECORD_USER, 'email');

        expect($rule->passes('email', null, []))->toBeTrue()
            ->and($rule->passes('email', '', []))->toBeTrue()
            ->and($rule->passes('email', ['a@example.com'], []))->toBeFalse()
            ->and($lookup->calls)->toBe([]);
    });
});

it('runs Exists and Unique through the validator', function (): void {
    $rules = new RecordRules(recordLookup());

    $errors = new Validator()->validate(
        ['channel_id' => 99, 'email' => 'grace@example.com'],
        [
            'channel_id' => ['required', $rules->exists(RECORD_CHANNEL)],
            'email' => ['required', 'email', $rules->unique(RECORD_USER, 'email')],
        ],
    );

    expect($errors->get('channel_id'))->toBe(['The selected channel_id is invalid.'])
        ->and($errors->get('email'))->toBe(['The email has already been taken.']);
});
