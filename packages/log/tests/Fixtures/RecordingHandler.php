<?php

declare(strict_types=1);

namespace Marko\Log\Tests\Fixtures;

use ArrayObject;
use Marko\Log\Contracts\LogHandlerInterface;
use Marko\Log\LogRecord;

/**
 * Collects every handled record; a shared log array records the order across handlers.
 */
class RecordingHandler implements LogHandlerInterface
{
    /** @var list<LogRecord> */
    public array $records = [];

    /**
     * @param ArrayObject<int, string>|null $sequence Shared across handlers to record the call order
     */
    public function __construct(
        private string $name = 'recording',
        private ?ArrayObject $sequence = null,
    ) {}

    public function handle(
        LogRecord $record,
    ): void {
        $this->records[] = $record;
        $this->sequence?->append($this->name);
    }
}
