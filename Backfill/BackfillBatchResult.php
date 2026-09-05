<?php
declare(strict_types=1);

namespace phpbbseo\framework\Backfill;

/**
 * Value object representing the outcome of a single backfill batch execution.
 */
class BackfillBatchResult
{
    public function __construct(
        public readonly int $processed,
        public readonly int $lastId,
        public readonly int $remaining,
        public readonly bool $completed,
        public readonly int $failed = 0,
        public readonly float $elapsed = 0.0
    ) {}

    /**
     * Convert result to array for JSON responses and API payloads.
     *
     * @return array{processed: int, last_id: int, remaining: int, completed: bool, failed: int, elapsed: float}
     */
    public function toArray(): array
    {
        return [
            'processed' => $this->processed,
            'last_id'   => $this->lastId,
            'remaining' => $this->remaining,
            'completed' => $this->completed,
            'failed'    => $this->failed,
            'elapsed'   => $this->elapsed,
        ];
    }
}
