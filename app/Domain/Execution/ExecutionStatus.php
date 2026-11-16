<?php

namespace App\Domain\Execution;

enum ExecutionStatus: string
{
    case QUEUED = 'queued';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
    case CANCELLED = 'cancelled';

    /** @return list<string> */
    public static function activeValues(): array
    {
        return [self::QUEUED->value, self::IN_PROGRESS->value];
    }
}
