<?php

declare(strict_types=1);

use Marko\Validation\Contracts\ImplicitRuleInterface;
use Marko\Validation\Contracts\RuleInterface;
use Marko\Validation\Contracts\WildcardAwareRuleInterface;
use Marko\Validation\Rules\Accepted;
use Marko\Validation\Rules\Declined;
use Marko\Validation\Rules\Present;
use Marko\Validation\Rules\Prohibited;
use Marko\Validation\Rules\Required;
use Marko\Validation\Rules\RequiredIf;
use Marko\Validation\Rules\RequiredUnless;
use Marko\Validation\Rules\RequiredWith;
use Marko\Validation\Rules\RequiredWithout;
use Marko\Validation\Validation\DataPath;
use Marko\Validation\Validation\FieldReference;
use Marko\Validation\Validation\Validator;

function alwaysFailingRule(): RuleInterface
{
    return new class () implements RuleInterface
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
            return "The $field field always fails.";
        }
    };
}

describe('DataPath::has', function (): void {
    it('tells a present key from an absent one', function (): void {
        $data = ['name' => 'Ada', 'empty' => ''];

        expect(DataPath::has($data, 'name'))->toBeTrue()
            ->and(DataPath::has($data, 'empty'))->toBeTrue()
            ->and(DataPath::has($data, 'missing'))->toBeFalse();
    });

    it('treats a key holding an explicit null as present', function (): void {
        expect(DataPath::has(['middle_name' => null], 'middle_name'))->toBeTrue()
            ->and(DataPath::get(['middle_name' => null], 'middle_name'))->toBeNull();
    });

    it('walks nested paths', function (): void {
        $data = ['user' => ['address' => ['city' => null]], 'scalar' => 'x'];

        expect(DataPath::has($data, 'user.address.city'))->toBeTrue()
            ->and(DataPath::has($data, 'user.address.zip'))->toBeFalse()
            ->and(DataPath::has($data, 'user.phone.number'))->toBeFalse()
            ->and(DataPath::has($data, 'scalar.inner'))->toBeFalse();
    });

    it('resolves wildcard-filled paths', function (): void {
        $data = ['items' => [['sku' => 'A'], ['qty' => 2]]];

        expect(DataPath::has($data, (string) DataPath::fillWildcards('items.*.sku', ['0'])))->toBeTrue()
            ->and(DataPath::has($data, (string) DataPath::fillWildcards('items.*.sku', ['1'])))->toBeFalse()
            ->and(DataPath::has($data, 'items.1'))->toBeTrue();
    });
});

describe('FieldReference', function (): void {
    it('reads and checks a dot path', function (): void {
        $reference = new FieldReference('same', 'user.email');

        expect($reference->has(['user' => ['email' => null]]))->toBeTrue()
            ->and($reference->has(['user' => []]))->toBeFalse()
            ->and($reference->get(['user' => ['email' => 'a@example.com']]))->toBe('a@example.com');
    });

    it('fills its wildcards with the row indexes', function (): void {
        expect(new FieldReference('same', 'items.*.sku')->forWildcardIndexes('items.*.check', ['4'])->path)
            ->toBe('items.4.sku');
    });

    it('throws when the path has more wildcards than the key', function (): void {
        new FieldReference('required_with', 'items.*.sku')->forWildcardIndexes('code', []);
    })->throws(
        InvalidArgumentException::class,
        'The required_with:items.*.sku rule has more wildcards than the code key it validates',
    );
});

describe('ImplicitRuleInterface', function (): void {
    it('is implemented by required, which always requires a value', function (): void {
        expect(new Required())->toBeInstanceOf(ImplicitRuleInterface::class)
            ->and(new Required()->requiresValue('name', []))->toBeTrue();
    });

    it('runs a custom implicit rule on an absent field', function (): void {
        $rule = new class () implements ImplicitRuleInterface
        {
            public function passes(
                string $field,
                mixed $value,
                array $data,
            ): bool {
                return ($data['country'] ?? null) !== 'US' || $value !== null;
            }

            public function message(
                string $field,
                mixed $value,
            ): string {
                return "The $field field is needed for US addresses.";
            }

            public function requiresValue(
                string $field,
                array $data,
            ): bool {
                return false;
            }
        };

        expect(new Validator()->validate(['country' => 'US'], ['state' => [$rule]])->all())
            ->toBe(['state' => ['The state field is needed for US addresses.']])
            ->and(new Validator()->passes(['country' => 'CA'], ['state' => [$rule]]))->toBeTrue();
    });

    it('still skips ordinary custom rules on an empty value', function (): void {
        expect(new Validator()->passes([], ['field' => [alwaysFailingRule()]]))->toBeTrue()
            ->and(new Validator()->passes(['field' => null], ['field' => [alwaysFailingRule()]]))->toBeTrue();
    });
});

describe('sometimes', function (): void {
    it('skips every rule when the key is absent', function (): void {
        expect(new Validator()->passes([], ['email' => 'sometimes|required|email']))->toBeTrue();
    });

    it('validates a present key', function (): void {
        expect(new Validator()->validate(['email' => 'nope'], ['email' => 'sometimes|required|email'])->all())
            ->toBe(['email' => ['The email field must be a valid email address.']]);
    });

    it('validates a present key holding an explicit null', function (): void {
        expect(new Validator()->validate(['email' => null], ['email' => 'sometimes|required|email'])->all())
            ->toBe(['email' => [
                'The email field is required.',
            ]]);
    });

    it('checks presence per row inside a wildcard key', function (): void {
        $errors = new Validator()->validate(
            ['items' => [['qty' => 2], ['qty' => null], []]],
            ['items.*.qty' => 'sometimes|required|integer'],
        );

        expect($errors->all())->toBe(['items.1.qty' => [
            'The items.1.qty field is required.',
        ]]);
    });
});

describe('present', function (): void {
    it('passes when the key exists, even empty or null', function (): void {
        $validator = new Validator();

        expect($validator->passes(['tags' => []], ['tags' => 'present']))->toBeTrue()
            ->and($validator->passes(['tags' => null], ['tags' => 'present']))->toBeTrue()
            ->and($validator->passes(['tags' => ''], ['tags' => 'present|array']))->toBeTrue();
    });

    it('fails when the key is absent', function (): void {
        expect(new Validator()->validate([], ['tags' => 'present|array'])->all())
            ->toBe(['tags' => ['The tags field must be present.']]);
    });

    it('does not make the other rules run on an empty value', function (): void {
        expect(new Present()->requiresValue('tags', []))->toBeFalse();
    });
});

describe('prohibited', function (): void {
    it('passes when the key is absent or empty', function (): void {
        $validator = new Validator();

        expect($validator->passes([], ['role' => 'prohibited']))->toBeTrue()
            ->and($validator->passes(['role' => null], ['role' => 'prohibited']))->toBeTrue()
            ->and($validator->passes(['role' => ' '], ['role' => 'prohibited']))->toBeTrue()
            ->and($validator->passes(['role' => []], ['role' => 'prohibited']))->toBeTrue();
    });

    it('fails when the field has a value', function (): void {
        expect(new Validator()->validate(['role' => 'admin'], ['role' => 'prohibited'])->all())
            ->toBe(['role' => ['The role field is prohibited.']])
            ->and(new Validator()->fails(['role' => 0], ['role' => 'prohibited']))->toBeTrue();
    });

    it('does not require a value', function (): void {
        expect(new Prohibited()->requiresValue('role', []))->toBeFalse();
    });
});

describe('accepted and declined', function (): void {
    it('accepts yes, on, 1, true and their string forms', function (mixed $value): void {
        expect(new Validator()->passes(['terms' => $value], ['terms' => 'accepted']))->toBeTrue();
    })->with(['yes', 'on', '1', 1, true, 'true']);

    it('rejects anything else, including an absent key', function (mixed $value): void {
        expect(new Validator()->validate(['terms' => $value], ['terms' => 'accepted'])->all())
            ->toBe(['terms' => ['The terms field must be accepted.']]);
    })->with(['no', 'off', '0', 0, false, 'false', null, '', 'YES', 2]);

    it('fails accepted when the key is absent', function (): void {
        expect(new Validator()->validate([], ['terms' => 'accepted'])->all())
            ->toBe(['terms' => ['The terms field must be accepted.']]);
    });

    it('declines no, off, 0, false and their string forms', function (mixed $value): void {
        expect(new Validator()->passes(['marketing' => $value], ['marketing' => 'declined']))->toBeTrue();
    })->with(['no', 'off', '0', 0, false, 'false']);

    it('rejects anything else for declined, including an absent key', function (mixed $value): void {
        expect(new Validator()->validate(['marketing' => $value], ['marketing' => 'declined'])->all())
            ->toBe(['marketing' => ['The marketing field must be declined.']]);
    })->with(['yes', 'on', '1', 1, true, 'true', null, '']);

    it('fails declined when the key is absent', function (): void {
        expect(new Validator()->validate([], ['marketing' => 'declined'])->all())
            ->toBe(['marketing' => ['The marketing field must be declined.']]);
    });

    it('does not make the other rules run on an empty value', function (): void {
        expect(new Accepted()->requiresValue('terms', []))->toBeFalse()
            ->and(new Declined()->requiresValue('terms', []))->toBeFalse();
    });
});

describe('required_if and required_unless', function (): void {
    it('requires the field when the other field has one of the values', function (): void {
        $rules = ['url' => 'required_if:type,video,audio|url'];

        expect(new Validator()->validate(['type' => 'video'], $rules)->all())
            ->toBe(['url' => [
                'The url field is required when type is video, audio.',
            ]])
            ->and(new Validator()->fails(['type' => 'audio', 'url' => ''], $rules))->toBeTrue()
            ->and(new Validator()->passes(['type' => 'video', 'url' => 'https://example.com'], $rules))->toBeTrue();
    });

    it('skips the field and its other rules when the condition is false', function (): void {
        $rules = ['url' => ['required_if:type,video', alwaysFailingRule()]];

        expect(new Validator()->passes(['type' => 'text'], $rules))->toBeTrue()
            ->and(new Validator()->passes([], $rules))->toBeTrue()
            ->and(new Validator()->fails(['type' => 'text', 'url' => 'https://example.com'], $rules))->toBeTrue();
    });

    it('runs the other rules on the empty value when the condition is true', function (): void {
        $rules = ['url' => ['required_if:type,video', alwaysFailingRule()]];

        expect(new Validator()->validate(['type' => 'video'], $rules)->all())
            ->toBe(['url' => ['The url field is required when type is video.', 'The url field always fails.']]);
    });

    it('compares booleans, numbers and null with their string forms', function (): void {
        $validator = new Validator();

        expect($validator->fails(['subscribe' => true], ['email' => 'required_if:subscribe,true']))->toBeTrue()
            ->and($validator->fails(['subscribe' => true], ['email' => 'required_if:subscribe,1']))->toBeTrue()
            ->and($validator->passes(['subscribe' => false], ['email' => 'required_if:subscribe,1']))->toBeTrue()
            ->and($validator->fails(['subscribe' => false], ['email' => 'required_if:subscribe,0']))->toBeTrue()
            ->and($validator->fails(['count' => 3], ['email' => 'required_if:count,3']))->toBeTrue()
            ->and($validator->fails(['parent' => null], ['email' => 'required_if:parent,null']))->toBeTrue()
            ->and($validator->passes(['tags' => ['a']], ['email' => 'required_if:tags,a']))->toBeTrue();
    });

    it('accepts typed values when built directly', function (): void {
        $rule = new RequiredIf('subscribe', true);

        expect($rule->requiresValue('email', ['subscribe' => true]))->toBeTrue()
            ->and($rule->requiresValue('email', ['subscribe' => 'true']))->toBeTrue()
            ->and($rule->requiresValue('email', ['subscribe' => false]))->toBeFalse()
            ->and($rule->message('email', null))->toBe('The email field is required when subscribe is true.');
    });

    it('requires the field unless the other field has one of the values', function (): void {
        $rules = ['reason' => 'required_unless:status,approved,pending'];

        expect(new Validator()->validate(['status' => 'rejected'], $rules)->all())
            ->toBe(['reason' => ['The reason field is required unless status is in approved, pending.']])
            ->and(new Validator()->fails([], $rules))->toBeTrue()
            ->and(new Validator()->passes(['status' => 'approved'], $rules))->toBeTrue()
            ->and(new Validator()->passes(['status' => 'rejected', 'reason' => 'Spam'], $rules))->toBeTrue();
    });

    it('resolves the other field inside a wildcard row', function (): void {
        $errors = new Validator()->validate(
            ['items' => [
                ['type' => 'video'],
                ['type' => 'text'],
                ['type' => 'video', 'url' => 'https://example.com'],
            ]],
            ['items.*.url' => 'required_if:items.*.type,video'],
        );

        expect($errors->all())->toBe([
            'items.0.url' => ['The items.0.url field is required when items.0.type is video.'],
        ]);
    });

    it('is wildcard aware and implicit', function (): void {
        expect(new RequiredIf('a', 'b'))->toBeInstanceOf(ImplicitRuleInterface::class)
            ->and(new RequiredIf('a', 'b'))->toBeInstanceOf(WildcardAwareRuleInterface::class)
            ->and(new RequiredUnless('a', 'b'))->toBeInstanceOf(ImplicitRuleInterface::class)
            ->and(new RequiredUnless('a', 'b'))->toBeInstanceOf(WildcardAwareRuleInterface::class);
    });

    it('throws when the other field or the values are missing', function (string $rule): void {
        new Validator()->validate([], ['url' => $rule]);
    })->with(['required_if', 'required_if:type', 'required_if:', 'required_unless', 'required_unless:type'])
        ->throws(InvalidArgumentException::class, 'needs a field and at least one value');

    it('throws up front when the other field has more wildcards than the key', function (): void {
        new Validator()->validate([], ['url' => 'required_if:items.*.type,video']);
    })->throws(
        InvalidArgumentException::class,
        'The required_if:items.*.type rule has more wildcards than the url key it validates',
    );
});

describe('required_with and required_without', function (): void {
    it('requires the field when any of the other fields has a value', function (): void {
        $rules = ['password' => 'required_with:email,username|string'];

        expect(new Validator()->validate(['username' => 'ada'], $rules)->all())
            ->toBe(['password' => [
                'The password field is required when email / username is present.',
            ]])
            ->and(new Validator()->passes([], $rules))->toBeTrue()
            ->and(new Validator()->passes(['email' => '', 'username' => null], $rules))->toBeTrue()
            ->and(new Validator()->passes(['email' => 'a@example.com', 'password' => 'secret'], $rules))->toBeTrue();
    });

    it('requires the field when any of the other fields is empty or absent', function (): void {
        $rules = ['phone' => 'required_without:email,fax'];

        expect(new Validator()->validate(['email' => 'a@example.com'], $rules)->all())
            ->toBe(['phone' => ['The phone field is required when email / fax is not present.']])
            ->and(new Validator()->fails(['email' => 'a@example.com', 'fax' => ''], $rules))->toBeTrue()
            ->and(new Validator()->passes(['email' => 'a@example.com', 'fax' => '555'], $rules))->toBeTrue()
            ->and(new Validator()->passes(['phone' => '555'], $rules))->toBeTrue();
    });

    it('skips the other rules when the field is not required', function (): void {
        $rules = ['phone' => ['required_without:email', alwaysFailingRule()]];

        expect(new Validator()->passes(['email' => 'a@example.com'], $rules))->toBeTrue()
            ->and(new Validator()->validate([], $rules)->all())->toBe(['phone' => [
                'The phone field is required when email is not present.',
                'The phone field always fails.',
            ]]);
    });

    it('resolves the other fields inside a wildcard row', function (): void {
        $errors = new Validator()->validate(
            ['contacts' => [
                ['email' => 'a@example.com'],
                [],
                ['email' => '', 'phone' => '555'],
            ]],
            [
                'contacts.*.name' => 'required_with:contacts.*.email,contacts.*.phone',
                'contacts.*.phone' => 'required_without:contacts.*.email',
            ],
        );

        expect($errors->all())->toBe([
            'contacts.0.name' => ['The contacts.0.name field is required when contacts.0.email / contacts.0.phone is present.'],
            'contacts.2.name' => ['The contacts.2.name field is required when contacts.2.email / contacts.2.phone is present.'],
            'contacts.1.phone' => ['The contacts.1.phone field is required when contacts.1.email is not present.'],
        ]);
    });

    it('is wildcard aware and implicit', function (): void {
        expect(new RequiredWith('a'))->toBeInstanceOf(ImplicitRuleInterface::class)
            ->and(new RequiredWith('a'))->toBeInstanceOf(WildcardAwareRuleInterface::class)
            ->and(new RequiredWithout('a'))->toBeInstanceOf(ImplicitRuleInterface::class)
            ->and(new RequiredWithout('a'))->toBeInstanceOf(WildcardAwareRuleInterface::class);
    });

    it('throws when no field is given', function (string $rule): void {
        new Validator()->validate([], ['phone' => $rule]);
    })->with(['required_with', 'required_with:', 'required_without', 'required_without:email,'])
        ->throws(InvalidArgumentException::class, 'needs at least one field');
});
