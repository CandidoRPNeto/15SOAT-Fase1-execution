<?php

namespace App\Application\Execution;

use App\Application\Messaging\OutboxRecorder;
use App\Domain\Execution\ExecutionMessage;
use App\Domain\Messaging\MessageEnvelope;
use Illuminate\Support\Facades\Log;

/**
 * Emits evt.execution.* through the outbox, echoing the saga id.
 *
 * Example: $emitter->emit(ExecutionMessage::REPAIR_STARTED, $task->saga_id, $task->correlation_id, ['service_order_id' => 7]);
 */
class ExecutionEventEmitter
{
    public function __construct(private readonly OutboxRecorder $outbox) {}

    /** @param array<string, mixed> $payload */
    public function emit(ExecutionMessage $type, string $sagaId, string $correlationId, array $payload): void
    {
        $this->outbox->record(MessageEnvelope::create($type->value, $sagaId, $correlationId, $payload));
        Log::info('execution.event', ['type' => $type->value, 'saga_id' => $sagaId, 'payload' => $payload]);
    }
}
