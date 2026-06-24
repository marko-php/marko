<?php

declare(strict_types=1);

use Marko\DevAi\Process\ConfirmationPrompterInterface;
use Marko\DevAi\Process\StdinPrompter;

function makeMemoryStream(string $content): mixed
{
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, $content);
    rewind($stream);

    return $stream;
}

function makeFakePrompter(bool $answer, bool $interactive = true): ConfirmationPrompterInterface
{
    return new readonly class ($answer, $interactive) implements ConfirmationPrompterInterface
    {
        public function __construct(
            private bool $answer,
            private bool $interactive,
        ) {}

        public function isInteractive(): bool
        {
            return $this->interactive;
        }

        public function confirm(string $question, bool $default): bool
        {
            return $this->answer;
        }
    };
}

describe('StdinPrompter', function (): void {
    it('returns true when the user answers yes', function (): void {
        $stream = makeMemoryStream("yes\n");
        $prompter = new StdinPrompter($stream);

        expect($prompter->confirm('Continue?', false))->toBeTrue();
    });

    it('returns false when the user answers no', function (): void {
        $stream = makeMemoryStream("no\n");
        $prompter = new StdinPrompter($stream);

        expect($prompter->confirm('Continue?', true))->toBeFalse();
    });

    it('returns the default when the answer is empty', function (): void {
        $streamTrue = makeMemoryStream("\n");
        $prompterTrue = new StdinPrompter($streamTrue);

        $streamFalse = makeMemoryStream("\n");
        $prompterFalse = new StdinPrompter($streamFalse);

        expect($prompterTrue->confirm('Continue?', true))->toBeTrue()
            ->and($prompterFalse->confirm('Continue?', false))->toBeFalse();
    });

    it('parses answers case-insensitively and ignores surrounding whitespace', function (): void {
        $streamY = makeMemoryStream("  Y  \n");
        $prompterY = new StdinPrompter($streamY);

        $streamYes = makeMemoryStream("  YES  \n");
        $prompterYes = new StdinPrompter($streamYes);

        $streamN = makeMemoryStream("  N  \n");
        $prompterN = new StdinPrompter($streamN);

        expect($prompterY->confirm('Continue?', false))->toBeTrue()
            ->and($prompterYes->confirm('Continue?', false))->toBeTrue()
            ->and($prompterN->confirm('Continue?', true))->toBeFalse();
    });

    it('reports not interactive when constructed in no-interaction mode', function (): void {
        $stream = makeMemoryStream('');
        $prompter = new StdinPrompter($stream, noInteraction: true);

        expect($prompter->isInteractive())->toBeFalse();
    });
});

describe('FakePrompter', function (): void {
    it('the fake prompter returns its scripted answer and configured interactivity', function (): void {
        $fakeTrue = makeFakePrompter(answer: true, interactive: true);
        $fakeFalse = makeFakePrompter(answer: false, interactive: false);

        expect($fakeTrue->confirm('Continue?', false))->toBeTrue()
            ->and($fakeTrue->isInteractive())->toBeTrue()
            ->and($fakeFalse->confirm('Continue?', true))->toBeFalse()
            ->and($fakeFalse->isInteractive())->toBeFalse();
    });
});
