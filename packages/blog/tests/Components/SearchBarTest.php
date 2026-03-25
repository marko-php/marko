<?php

declare(strict_types=1);

describe('Search Bar Component', function (): void {
    it('renders search form with GET method', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::search/bar', []);

        expect($html)->toContain('<form')
            ->and($html)->toMatch('/method\s*=\s*["\']get["\']/i');
    });

    it('has search input field with name q', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::search/bar', []);

        expect($html)->toContain('<input')
            ->and($html)->toMatch('/name\s*=\s*["\']q["\']/');
    });

    it('has submit button', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::search/bar', []);

        expect($html)->toMatch('/<button[^>]*type\s*=\s*["\']submit["\']/');
    });

    it('preserves current search query in input value', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::search/bar', [
            'query' => 'test search',
        ]);

        expect($html)->toMatch('/value\s*=\s*["\']test search["\']/');
    });

    it('has accessible labels and placeholder', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::search/bar', []);

        // Should have a label element
        expect($html)->toContain('<label')
            // Label should reference the input via for attribute
            ->and($html)->toMatch('/for\s*=\s*["\']search-input["\']/i')
            // Input should have matching id
            ->and($html)->toMatch('/id\s*=\s*["\']search-input["\']/i')
            // Input should have placeholder
            ->and($html)->toMatch('/placeholder\s*=\s*["\']/');
    });

    it('uses semantic HTML structure', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::search/bar', []);

        // Should use search role on form
        expect($html)->toMatch('/role\s*=\s*["\']search["\']/i')
            // Should have CSS class for styling (styling-agnostic)
            ->and($html)->toMatch('/class\s*=\s*["\']search-bar["\']/i');
    });
});
