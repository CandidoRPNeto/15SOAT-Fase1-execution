<?php

namespace App\Application\Execution;

use App\Domain\Execution\ExecutionPhase;
use App\Domain\Execution\ExecutionStatus;
use App\Domain\Execution\ExecutionTransitionException;
use App\Models\ExecutionTask;
use Illuminate\Support\Facades\DB;

/**
 * Mechanic actions on the shop floor: start/finish the current phase. Each
 * action and its saga event commit together (outbox).
 *
 * Example: $progress->start($task, ExecutionPhase::REPAIR, $mechanicId);
 */
class ProgressTask
{
    public function __construct(private readonly ExecutionEventEmitter $events) {}

    public function start(ExecutionTask $task, ExecutionPhase $phase, int $mechanicId): ExecutionTask
    {
        $this->guard($task, $phase, ExecutionStatus::QUEUED);

        return $this->apply($task, $phase, $mechanicId, ExecutionStatus::IN_PROGRESS, ['started_at' => now()], null);
    }

    public function finish(ExecutionTask $task, ExecutionPhase $phase, int $mechanicId, ?string $notes): ExecutionTask
    {
        $this->guard($task, $phase, ExecutionStatus::IN_PROGRESS);

        return $this->apply($task, $phase, $mechanicId, ExecutionStatus::DONE, ['finished_at' => now()], $notes);
    }

    private function guard(ExecutionTask $task, ExecutionPhase $phase, ExecutionStatus $expected): void
    {
        if ($task->phase !== $phase || $task->status !== $expected) {
            throw new ExecutionTransitionException(
                "OS {$task->service_order_number}: task is {$task->phase->value}/{$task->status->value}; expected {$phase->value}/{$expected->value}"
            );
        }
    }

    /** @param array<string, mixed> $timestamps */
    private function apply(ExecutionTask $task, ExecutionPhase $phase, int $mechanicId, ExecutionStatus $to, array $timestamps, ?string $notes): ExecutionTask
    {
        DB::connection('mongodb')->transaction(function () use ($task, $phase, $mechanicId, $to, $timestamps, $notes) {
            $task->fill(['status' => $to, 'mechanic_id' => $mechanicId, ...$timestamps]);
            $task->appendTimeline($to === ExecutionStatus::DONE ? "{$phase->value}_finished" : "{$phase->value}_started", $mechanicId, array_filter(['notes' => $notes]));
            $task->save();

            $event = $to === ExecutionStatus::DONE ? $phase->finishedEvent() : $phase->startedEvent();
            $this->events->emit($event, $task->saga_id, $task->correlation_id, array_filter([
                'service_order_id' => $task->service_order_id,
                'mechanic_id' => $mechanicId,
                'notes' => $notes,
            ], fn ($value) => $value !== null));
        });

        return $task;
    }
}
