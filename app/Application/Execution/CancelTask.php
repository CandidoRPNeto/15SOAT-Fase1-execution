<?php

namespace App\Application\Execution;

use App\Domain\Execution\ExecutionStatus;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\ExecutionTask;
use Illuminate\Support\Facades\Log;

/**
 * Compensation for cmd.execution.cancel_task. Succeeds when the task is
 * missing or already finished/cancelled.
 */
class CancelTask
{
    public function handle(MessageEnvelope $command): void
    {
        $task = ExecutionTask::where('service_order_id', (int) $command->payloadValue('service_order_id'))->first();

        if ($task === null || ! in_array($task->status->value, ExecutionStatus::activeValues(), true)) {
            Log::info('execution.cancel_noop', ['service_order_id' => $command->payloadValue('service_order_id')]);

            return;
        }

        $reason = (string) ($command->payload['reason'] ?? 'compensação da saga');
        $task->status = ExecutionStatus::CANCELLED;
        $task->cancel_reason = $reason;
        $task->appendTimeline('cancelled', null, ['reason' => $reason]);
        $task->save();
        Log::warning('execution.task_cancelled', ['service_order_id' => $task->service_order_id, 'reason' => $reason]);
    }
}
