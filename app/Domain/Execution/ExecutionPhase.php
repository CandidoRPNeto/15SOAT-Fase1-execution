<?php

namespace App\Domain\Execution;

enum ExecutionPhase: string
{
    case DIAGNOSIS = 'diagnosis';
    case REPAIR = 'repair';

    /** Approved repairs (client waiting, stock reserved) jump ahead of new diagnoses. */
    public function priority(): int
    {
        return $this === self::REPAIR ? 20 : 10;
    }

    public function startedEvent(): ExecutionMessage
    {
        return $this === self::REPAIR ? ExecutionMessage::REPAIR_STARTED : ExecutionMessage::DIAGNOSIS_STARTED;
    }

    public function finishedEvent(): ExecutionMessage
    {
        return $this === self::REPAIR ? ExecutionMessage::REPAIR_FINISHED : ExecutionMessage::DIAGNOSIS_COMPLETED;
    }
}
