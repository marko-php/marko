<?php

declare(strict_types=1);

namespace Marko\Blog\Tests\Services;

use Marko\Blog\Services\HoneypotValidator;

it('generates honeypot field name', function (): void {
    $validator = new HoneypotValidator();

    $fieldName = $validator->getFieldName();

    expect($fieldName)->toBeString()
        ->and($fieldName)->not->toBeEmpty();
});

it('validates submission passes when honeypot field is empty', function (): void {
    $validator = new HoneypotValidator();

    $result = $validator->validate('');

    expect($result)->toBeTrue();
});

it('validates submission fails when honeypot field has value', function (): void {
    $validator = new HoneypotValidator();

    $result = $validator->validate('spam-bot-filled-this');

    expect($result)->toBeFalse();
});

it('generates honeypot field HTML for forms', function (): void {
    $validator = new HoneypotValidator();

    $html = $validator->renderField();

    expect($html)->toContain('<input')
        ->and($html)->toContain('type="text"')
        ->and($html)->toContain($validator->getFieldName());
});

it('uses CSS to hide honeypot field from humans', function (): void {
    $validator = new HoneypotValidator();

    $html = $validator->renderField();

    // Should use CSS-based hiding, not display:none or visibility:hidden
    // (which some bots detect), using positioning off-screen instead
    expect($html)->toContain('style=')
        ->and($html)->toContain('position:absolute')
        ->and($html)->toContain('left:-9999px');
});

it('rotates field name periodically for obfuscation', function (): void {
    // Field name should change based on time period (e.g., daily)
    // This makes it harder for bots to learn the field name
    $validator = new HoneypotValidator();

    $fieldName = $validator->getFieldName();

    // Field name should include a rotating date-based component
    // Verify it contains today's date-based hash by checking the format
    expect($fieldName)->toMatch('/^hp_[a-f0-9]{8}$/');

    // The field name should be consistent within the same day
    $fieldName2 = $validator->getFieldName();
    expect($fieldName2)->toBe($fieldName);

    // Verify rotation works by testing with a custom secret/date combination
    $validatorWithSecret = new HoneypotValidator('test-secret');
    $secretFieldName = $validatorWithSecret->getFieldName();

    expect($secretFieldName)->toMatch('/^hp_[a-f0-9]{8}$/')
        ->and($secretFieldName)->not->toBe($fieldName);
});
