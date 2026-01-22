<?php

declare(strict_types=1);

use Marko\Core\Exceptions\MarkoException;
use Marko\Queue\Exceptions\JobFailedException;
use Marko\Queue\Exceptions\QueueException;
use Marko\Queue\Exceptions\SerializationException;

describe('QueueException', function (): void {
    it('has noDriverInstalled factory method', function (): void {
        $exception = QueueException::noDriverInstalled();

        expect($exception)->toBeInstanceOf(QueueException::class)
            ->and($exception)->toBeInstanceOf(MarkoException::class)
            ->and($exception->getMessage())->toBe('No queue driver installed.')
            ->and($exception->getContext())->toContain('QueueInterface')
            ->and($exception->getSuggestion())->toContain('composer require marko/queue-sync');
    });

    it('has configFileNotFound factory method', function (): void {
        $exception = QueueException::configFileNotFound('/path/to/config/queue.php');

        expect($exception)->toBeInstanceOf(QueueException::class)
            ->and($exception->getMessage())->toContain('queue.php')
            ->and($exception->getContext())->toContain('/path/to/config/queue.php')
            ->and($exception->getSuggestion())->toContain('config');
    });
});

describe('JobFailedException', function (): void {
    it('has fromException factory method', function (): void {
        $originalException = new RuntimeException('Database connection lost');
        $exception = JobFailedException::fromException('App\Jobs\SendEmail', $originalException);

        expect($exception)->toBeInstanceOf(JobFailedException::class)
            ->and($exception)->toBeInstanceOf(QueueException::class)
            ->and($exception->getPrevious())->toBe($originalException);
    });

    it('includes job class name in message', function (): void {
        $originalException = new RuntimeException('Something went wrong');
        $exception = JobFailedException::fromException('App\Jobs\ProcessPayment', $originalException);

        expect($exception->getMessage())->toContain('App\Jobs\ProcessPayment');
    });
});

describe('SerializationException', function (): void {
    it('has invalidJobData factory method', function (): void {
        $exception = SerializationException::invalidJobData('corrupted payload');

        expect($exception)->toBeInstanceOf(SerializationException::class)
            ->and($exception)->toBeInstanceOf(QueueException::class)
            ->and($exception->getMessage())->toContain('Invalid job data')
            ->and($exception->getContext())->toContain('corrupted payload')
            ->and($exception->getSuggestion())->not->toBeEmpty();
    });

    it('has unserializableClosure factory method', function (): void {
        $exception = SerializationException::unserializableClosure('App\Jobs\SendEmail');

        expect($exception)->toBeInstanceOf(SerializationException::class)
            ->and($exception->getMessage())->toContain('closure')
            ->and($exception->getContext())->toContain('App\Jobs\SendEmail')
            ->and($exception->getSuggestion())->toContain('Closure');
    });
});

it('Exception factories include proper context', function (): void {
    // Test all exception factories include context, message, and suggestion
    $queueNoDriver = QueueException::noDriverInstalled();
    expect($queueNoDriver->getMessage())->not->toBeEmpty()
        ->and($queueNoDriver->getContext())->not->toBeEmpty()
        ->and($queueNoDriver->getSuggestion())->not->toBeEmpty();

    $queueConfigNotFound = QueueException::configFileNotFound('/config/queue.php');
    expect($queueConfigNotFound->getMessage())->not->toBeEmpty()
        ->and($queueConfigNotFound->getContext())->toContain('/config/queue.php')
        ->and($queueConfigNotFound->getSuggestion())->not->toBeEmpty();

    $jobFailed = JobFailedException::fromException(
        'App\Jobs\TestJob',
        new RuntimeException('Test error'),
    );
    expect($jobFailed->getMessage())->toContain('App\Jobs\TestJob')
        ->and($jobFailed->getMessage())->toContain('Test error')
        ->and($jobFailed->getContext())->toContain('App\Jobs\TestJob')
        ->and($jobFailed->getSuggestion())->not->toBeEmpty()
        ->and($jobFailed->getPrevious())->toBeInstanceOf(RuntimeException::class);

    $serializationInvalid = SerializationException::invalidJobData('missing class');
    expect($serializationInvalid->getMessage())->not->toBeEmpty()
        ->and($serializationInvalid->getContext())->toContain('missing class')
        ->and($serializationInvalid->getSuggestion())->not->toBeEmpty();

    $serializationClosure = SerializationException::unserializableClosure('App\Jobs\ClosureJob');
    expect($serializationClosure->getMessage())->not->toBeEmpty()
        ->and($serializationClosure->getContext())->toContain('App\Jobs\ClosureJob')
        ->and($serializationClosure->getSuggestion())->not->toBeEmpty();
});
