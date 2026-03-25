<?php

declare(strict_types=1);

describe('Comment Form Component', function (): void {
    it('renders form with POST action to comment endpoint', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '<input type="text" name="hp_abc123" value="" autocomplete="off" tabindex="-1" />',
        ]);

        expect($html)->toContain('<form')
            ->and($html)->toMatch('/method\s*=\s*["\']post["\']/i')
            ->and($html)->toMatch('/action\s*=\s*["\'][^"\']*\/blog\/1\/comment["\']/');
    });

    it('includes name input field with label', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        expect($html)->toContain('<label')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-name["\']/i')
            ->and($html)->toMatch('/id\s*=\s*["\']comment-name["\']/i')
            ->and($html)->toMatch('/name\s*=\s*["\']name["\']/');
    });

    it('includes email input field with label', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        expect($html)->toContain('<label')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-email["\']/i')
            ->and($html)->toMatch('/id\s*=\s*["\']comment-email["\']/i')
            ->and($html)->toMatch('/name\s*=\s*["\']email["\']/');
    });

    it('includes content textarea with label', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        expect($html)->toContain('<label')
            ->and($html)->toContain('<textarea')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-content["\']/i')
            ->and($html)->toMatch('/id\s*=\s*["\']comment-content["\']/i')
            ->and($html)->toMatch('/name\s*=\s*["\']content["\']/');
    });

    it('includes hidden parent_id field for replies', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
            'parentId' => 42,
        ]);

        expect($html)->toMatch('/<input[^>]*type\s*=\s*["\']hidden["\']/i')
            ->and($html)->toMatch('/<input[^>]*name\s*=\s*["\']parent_id["\']/i')
            ->and($html)->toMatch('/value\s*=\s*["\']42["\']/');
    });

    it('includes honeypot field hidden by CSS', function (): void {
        $view = createBlogTestView();
        $honeypotField = '<div style="position:absolute;left:-9999px;"><input type="text" name="hp_abc123" value="" autocomplete="off" tabindex="-1" /></div>';

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => $honeypotField,
        ]);

        expect($html)->toContain($honeypotField);
    });

    it('includes submit button', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        expect($html)->toMatch('/<button[^>]*type\s*=\s*["\']submit["\']/');
    });

    it('shows validation error messages when provided', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
            'errors' => [
                'name' => 'Name is required',
                'email' => 'Invalid email format',
                'content' => 'Content must be at least 10 characters',
            ],
        ]);

        expect($html)->toContain('Name is required')
            ->and($html)->toContain('Invalid email format')
            ->and($html)->toContain('Content must be at least 10 characters');
    });

    it('preserves input values on validation failure', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
            'oldInput' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'content' => 'My comment text',
            ],
        ]);

        expect($html)->toMatch('/value\s*=\s*["\']John Doe["\']/i')
            ->and($html)->toMatch('/value\s*=\s*["\']john@example\.com["\']/i')
            ->and($html)->toContain('My comment text');
    });

    it('has proper form accessibility labels', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/form', [
            'postId' => 1,
            'honeypotField' => '',
        ]);

        // Each input should have an associated label with for attribute
        expect($html)->toMatch('/for\s*=\s*["\']comment-name["\']/i')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-email["\']/i')
            ->and($html)->toMatch('/for\s*=\s*["\']comment-content["\']/i')
            // Form should have aria-label for accessibility
            ->and($html)->toMatch('/aria-label\s*=\s*["\'].*comment.*["\']/i');
    });
});
