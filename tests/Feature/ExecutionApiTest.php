<?php

namespace Tests\Feature;

use App\Application\Messaging\InboxProcessor;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\ExecutionTask;
use App\Models\OutboxMessage;
use Tests\Support\GatewayHeaders;
use Tests\TestCase;

class ExecutionApiTest extends TestCase
{
    private function enqueue(int $orderId, string $phase = 'diagnosis'): void
    {
        app(InboxProcessor::class)->process(MessageEnvelope::create("cmd.execution.enqueue_{$phase}", "saga-{$orderId}", 'corr', [
            'service_order_id' => $orderId, 'service_order_number' => "OS-{$orderId}",
        ]));
    }

    private function lastEvent(): array
    {
        return OutboxMessage::orderBy('id', 'desc')->first()->body;
    }

    public function test_requires_gateway_identity_and_staff_role(): void
    {
        $this->getJson('/api/v1/executions')->assertUnauthorized();
        $this->withHeaders(GatewayHeaders::client())->getJson('/api/v1/executions')->assertForbidden();
    }

    public function test_queue_lists_repairs_before_diagnoses_and_hides_finished(): void
    {
        $this->enqueue(1);
        $this->enqueue(2);
        ExecutionTask::where('service_order_id', 2)->update(['phase' => 'repair', 'priority' => 20]);
        $this->enqueue(3);
        ExecutionTask::where('service_order_id', 3)->update(['status' => 'done']);

        $this->withHeaders(GatewayHeaders::staff('receptionist'))->getJson('/api/v1/executions')
            ->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.service_order_id', 2)
            ->assertJsonPath('data.1.service_order_id', 1);
    }

    public function test_mechanic_runs_diagnosis_and_saga_events_follow(): void
    {
        $this->enqueue(7);

        $this->withHeaders(GatewayHeaders::staff())->postJson('/api/v1/executions/7/diagnosis/start')
            ->assertOk()->assertJsonPath('status', 'in_progress')->assertJsonPath('mechanic_id', 1);
        $this->assertSame('evt.execution.diagnosis_started', $this->lastEvent()['type']);

        $this->withHeaders(GatewayHeaders::staff())->postJson('/api/v1/executions/7/diagnosis/finish', ['notes' => 'pastilhas gastas'])
            ->assertOk()->assertJsonPath('status', 'done');
        $event = $this->lastEvent();
        $this->assertSame('evt.execution.diagnosis_completed', $event['type']);
        $this->assertSame('saga-7', $event['saga_id']);
        $this->assertSame('pastilhas gastas', $event['payload']['notes']);
    }

    public function test_mechanic_runs_repair(): void
    {
        $this->enqueue(7);
        ExecutionTask::sole()->update(['status' => 'done']);
        $this->enqueue(7, 'repair');

        $this->withHeaders(GatewayHeaders::staff())->postJson('/api/v1/executions/7/repair/start')->assertOk();
        $this->assertSame('evt.execution.repair_started', $this->lastEvent()['type']);
        $this->withHeaders(GatewayHeaders::staff())->postJson('/api/v1/executions/7/repair/finish')->assertOk();
        $this->assertSame('evt.execution.repair_finished', $this->lastEvent()['type']);

        $this->withHeaders(GatewayHeaders::staff())->getJson('/api/v1/executions/7')
            ->assertOk()->assertJsonPath('phase', 'repair')->assertJsonCount(4, 'timeline');
    }

    public function test_wrong_phase_or_status_is_422_and_emits_nothing(): void
    {
        $this->enqueue(7);
        $before = OutboxMessage::count();

        $this->withHeaders(GatewayHeaders::staff())->postJson('/api/v1/executions/7/repair/start')
            ->assertUnprocessable()->assertJsonPath('message', 'OS OS-7: task is diagnosis/queued; expected repair/queued');
        $this->withHeaders(GatewayHeaders::staff())->postJson('/api/v1/executions/7/diagnosis/finish')->assertUnprocessable();
        $this->assertSame($before, OutboxMessage::count());
    }

    public function test_receptionist_cannot_act_unknown_order_is_404_and_notes_validated(): void
    {
        $this->enqueue(7);

        $this->withHeaders(GatewayHeaders::staff('receptionist'))->postJson('/api/v1/executions/7/diagnosis/start')->assertForbidden();
        $this->withHeaders(GatewayHeaders::staff())->getJson('/api/v1/executions/999')->assertNotFound();
        $this->withHeaders(GatewayHeaders::staff())->postJson('/api/v1/executions/7/diagnosis/start')->assertOk();
        $this->withHeaders(GatewayHeaders::staff())->postJson('/api/v1/executions/7/diagnosis/finish', ['notes' => str_repeat('x', 2001)])->assertUnprocessable();
    }
}
