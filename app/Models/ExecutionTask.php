<?php

namespace App\Models;

use App\Domain\Execution\ExecutionPhase;
use App\Domain\Execution\ExecutionStatus;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * One document per service order: current phase/status plus an embedded,
 * append-only timeline (ADR-012).
 *
 * @property string $id
 * @property int $service_order_id
 * @property string $service_order_number
 * @property ExecutionPhase $phase
 * @property ExecutionStatus $status
 * @property int $priority
 * @property int|null $mechanic_id
 * @property string $saga_id
 * @property string $correlation_id
 * @property string|null $notes
 * @property string|null $cancel_reason
 * @property list<array<string, mixed>> $timeline
 * @property Carbon|null $queued_at
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class ExecutionTask extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'execution_tasks';

    protected $fillable = [
        'service_order_id', 'service_order_number', 'phase', 'status', 'priority', 'mechanic_id', 'saga_id',
        'correlation_id', 'notes', 'cancel_reason', 'timeline', 'queued_at', 'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'phase' => ExecutionPhase::class,
            'status' => ExecutionStatus::class,
            'queued_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @param array<string, mixed> $detail */
    public function appendTimeline(string $event, ?int $actorId, array $detail = []): void
    {
        $this->timeline = [
            ...($this->timeline ?? []),
            ['at' => now()->toIso8601String(), 'event' => $event, 'actor_id' => $actorId, 'detail' => $detail],
        ];
    }
}
