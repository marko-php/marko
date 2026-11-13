<?php

declare(strict_types=1);

use Marko\View\Slot;

describe('Slot', function (): void {
    it('builds the placeholder a layout replaces when it fills a sub-slot', function (): void {
        expect(Slot::placeholder('tab.details'))->toBe('{slot tab.details}{/slot}');
    });

    it('renders the pre-rendered HTML of a named slot', function (): void {
        expect(Slot::render(['content' => '<h1>Hello</h1>'], 'content'))->toBe('<h1>Hello</h1>');
    });

    it('renders an empty string for a slot with no content', function (): void {
        expect(Slot::render(['content' => '<h1>Hello</h1>'], 'sidebar'))->toBe('');
    });

    it('renders the placeholder when the template was given no slots', function (): void {
        expect(Slot::render(null, 'tab.reviews'))->toBe('{slot tab.reviews}{/slot}');
    });
});
