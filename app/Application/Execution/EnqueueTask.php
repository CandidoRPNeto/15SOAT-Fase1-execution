<?php

namespace App\Application\Execution;

use App\Domain\Execution\ExecutionMessage;
use App\Domain\Execution\ExecutionPhase;
use App\Domain\Execution\ExecutionStatus;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\ExecutionTask;

/**
 * Handles cmd.execution.enqueue_diagnosis / enqueue_repair. Refuses when the
 * shop floor is at capacity (EXECUTION_QUEUE_CAPACITY) so the saga
 * compensates instead of piling up work. Idempotent by service_order_id + phase.
 */
class EnqueueTask
{
    public function __construct(
        private readonly ExecutionEventEmitter $events,
        private readonly int $capacity,
    ) {}

    public function handle(MessageEnvelope $command, ExecutionPhase $phase): void
    {
        $orderId = (int) $command->payloadValue('service_order_id');
        $task = ExecutionTask::where('service_order_id', $orderId)->first();

        if ($task !== null && $task->phase === $phase && $task->status !== ExecutionStatus::CANCELLED) {
            $this->reply($command, $phase, true, ['task_id' => $task->id]);

            return;
        }

        if ($this->activeCount() >= $this->capacity) {
            $this->reply($command, $phase, false, ['reason' => "fila de execução lotada (capacidade {$this->capacity})"]);

            return;
        }

        $task = $this->queue($task ?? new ExecutionTask, $command, $phase);
        $this->reply($command, $phase, true, ['task_id' => $task->id]);
    }

    private function queue(ExecutionTask $task, MessageEnvelope $command, ExecutionPhase $phase): ExecutionTask
    {
        $task->fill([
            'service_order_id' => (int) $command->payloadValue('service_order_id'),
            'service_order_number' => (string) $command->payloadValue('service_order_number'),
            'phase' => $phase,
            'status' => ExecutionStatus::QUEUED,
            'priority' => $phase->priority(),
            'saga_id' => $command->sagaId,
            'correlation_id' => $command->correlationId,
            'queued_at' => now(),
            'started_at' => null,
            'finished_at' => null,
        ]);
        $task->notes ??= $command->payload['notes'] ?? null;
        $task->appendTimeline("{$phase->value}_queued", null);
        $task->save();

        return $task;
    }

    private function activeCount(): int
    {
        return ExecutionTask::whereIn('status', ExecutionStatus::activeValues())->count();
    }

    /** @param array<string, mixed> $extra */
    private function reply(MessageEnvelope $command, ExecutionPhase $phase, bool $ok, array $extra): void
    {
        $type = match ([$phase, $ok]) {
            [ExecutionPhase::DIAGNOSIS, true] => ExecutionMessage::DIAGNOSIS_ENQUEUED,
            [ExecutionPhase::DIAGNOSIS, false] => ExecutionMessage::DIAGNOSIS_ENQUEUE_FAILED,
            [ExecutionPhase::REPAIR, true] => ExecutionMessage::REPAIR_ENQUEUED,
            [ExecutionPhase::REPAIR, false] => ExecutionMessage::REPAIR_ENQUEUE_FAILED,
        };

        $this->events->emit($type, $command->sagaId, $command->correlationId, [
            'service_order_id' => (int) $command->payloadValue('service_order_id'),
            ...$extra,
        ]);
    }
}
