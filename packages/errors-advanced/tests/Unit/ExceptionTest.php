<?php

declare(strict_types=1);

use Marko\ErrorsAdvanced\Exceptions\AdvancedErrorHandlerException;

describe('AdvancedErrorHandlerException', function () {
    it('creates AdvancedErrorHandlerException', function () {
        $exception = new AdvancedErrorHandlerException('Test error message');

        expect($exception)->toBeInstanceOf(AdvancedErrorHandlerException::class)
            ->and($exception->getMessage())->toBe('Test error message');
    });

    it('includes context field', function () {
        $exception = new AdvancedErrorHandlerException(
            message: 'Test error',
            context: 'Additional context about the error',
        );

        expect($exception->getContext())->toBe('Additional context about the error');
    });

    it('includes suggestion field', function () {
        $exception = new AdvancedErrorHandlerException(
            message: 'Test error',
            suggestion: 'Try doing this instead',
        );

        expect($exception->getSuggestion())->toBe('Try doing this instead');
    });

    it('provides factory method for common cases', function () {
        $exception = AdvancedErrorHandlerException::handlerNotFound('custom_handler');

        expect($exception)->toBeInstanceOf(AdvancedErrorHandlerException::class)
            ->and($exception->getMessage())->toContain('custom_handler')
            ->and($exception->getContext())->not->toBeEmpty()
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });
});
