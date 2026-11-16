<?php

namespace Tests\Feature;

use App\Application\Messaging\InboxProcessor;
use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\ExecutionTask;
use App\Models\OutboxMessage;
use InvalidArgumentException;
use Tests\TestCase;

class ExecutionCommandsTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    private function send(string $type, int $orderId, array $payload = []): void
    {
        app(InboxProcessor::class)->process(MessageEnvelope::create($type, "saga-{$orderId}", 'corr', [
            'service_order_id' => $orderId, 'service_order_number' => "OS-{$orderId}", ...$payload,
        ]));
    }

    /** @return list<array<string, mixed>> */
    private function emitted(): array
    {
        return OutboxMessage::orderBy('id')->get()->map(fn (OutboxMessage $m) => $m->body)->all();
    }

    public function test_enqueue_diagnosis_creates_queued_task_and_replies(): void
    {
        $this->send('cmd.execution.enqueue_diagnosis', 7, ['notes' => 'barulho no freio']);

        $task = ExecutionTask::sole();
        $this->assertSame('diagnosis', $task->phase->value);
        $this->assertSame('queued', $task->status->value);
        $this->assertSame('barulho no freio', $task->notes);
        $this->assertSame('diagnosis_queued', $task->timeline[0]['event']);
        $reply = $this->emitted()[0];
        $this->assertSame('evt.execution.diagnosis_enqueued', $reply['type']);
        $this->assertSame('saga-7', $reply['saga_id']);
        $this->assertSame($task->id, $reply['payload']['task_id']);
    }

    public function test_resent_enqueue_replies_without_duplicating(): void
    {
        $this->send('cmd.execution.enqueue_diagnosis', 7);
        $this->send('cmd.execution.enqueue_diagnosis', 7);

        $this->assertSame(1, ExecutionTask::count());
        $this->assertCount(2, $this->emitted());
    }

    public function test_full_queue_refuses_work_so_the_saga_compensates(): void
    {
        foreach ([1, 2, 3] as $id) {
            $this->send('cmd.execution.enqueue_diagnosis', $id);
        }
        $this->send('cmd.execution.enqueue_diagnosis', 4);

        $reply = $this->emitted()[3];
        $this->assertSame('evt.execution.diagnosis_enqueue_failed', $reply['type']);
        $this->assertSame('fila de execução lotada (capacidade 3)', $reply['payload']['reason']);
        $this->assertSame(3, ExecutionTask::count());
    }

    public function test_enqueue_repair_moves_task_to_repair_with_higher_priority(): void
    {
        $this->send('cmd.execution.enqueue_diagnosis', 7);
        ExecutionTask::sole()->update(['status' => 'done']);

        $this->send('cmd.execution.enqueue_repair', 7);

        $task = ExecutionTask::sole();
        $this->assertSame('repair', $task->phase->value);
        $this->assertSame('queued', $task->status->value);
        $this->assertSame(20, $task->priority);
        $this->assertSame('evt.execution.repair_enqueued', $this->emitted()[1]['type']);
    }

    public function test_repair_refused_when_queue_is_full(): void
    {
        foreach ([1, 2, 3] as $id) {
            $this->send('cmd.execution.enqueue_diagnosis', $id);
        }
        $this->send('cmd.execution.enqueue_repair', 9);

        $this->assertSame('evt.execution.repair_enqueue_failed', $this->emitted()[3]['type']);
    }

    public function test_cancel_task_compensates_and_is_noop_when_missing(): void
    {
        $this->send('cmd.execution.cancel_task', 99, ['reason' => 'x']);
        $this->send('cmd.execution.enqueue_diagnosis', 7);
        $this->send('cmd.execution.cancel_task', 7, ['reason' => 'estoque insuficiente']);

        $task = ExecutionTask::sole();
        $this->assertSame('cancelled', $task->status->value);
        $this->assertSame('estoque insuficiente', $task->cancel_reason);
        $this->assertSame('cancelled', collect($task->timeline)->last()['event']);
    }

    public function test_cancelled_task_can_be_requeued_by_a_new_saga_attempt(): void
    {
        $this->send('cmd.execution.enqueue_diagnosis', 7);
        $this->send('cmd.execution.cancel_task', 7);
        $this->send('cmd.execution.enqueue_diagnosis', 7);

        $this->assertSame('queued', ExecutionTask::sole()->status->value);
    }

    public function test_router_bindings_and_unknown_command(): void
    {
        $this->assertSame(['cmd.execution.*'], app(MessageRouter::class)->bindings());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported message type 'cmd.execution.unknown'");
        $this->send('cmd.execution.unknown', 1);
    }
}
