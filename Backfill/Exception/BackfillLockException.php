<?php
declare(strict_types=1);

namespace phpbbseo\framework\Backfill\Exception;

/**
 * Exception thrown when backfill lock acquisition fails due to concurrent execution.
 */
class BackfillLockException extends \RuntimeException
{
}
