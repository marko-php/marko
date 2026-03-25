<?php

declare(strict_types=1);

describe('Comment Verify Error View', function (): void {
    it('renders verify error template', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/verify-error', [
            'statusCode' => 400,
        ]);

        expect($html)->toContain('Verification Failed');
    });

    it('informs user the link is invalid or expired', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/verify-error', [
            'statusCode' => 400,
        ]);

        expect($html)->toMatch('/invalid|expired/i');
    });

    it('has semantic HTML structure', function (): void {
        $view = createBlogTestView();

        $html = $view->renderToString('blog::comment/verify-error', [
            'statusCode' => 400,
        ]);

        expect($html)->toContain('<main')
            ->and($html)->toContain('<h1');
    });
});
