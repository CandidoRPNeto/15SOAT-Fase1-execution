<?php

namespace App\Http\Resources;

use App\Models\ExecutionTask;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExecutionTask */
class ExecutionTaskResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'service_order_id' => $this->service_order_id,
            'service_order_number' => $this->service_order_number,
            'phase' => $this->phase->value,
            'status' => $this->status->value,
            'priority' => $this->priority,
            'mechanic_id' => $this->mechanic_id,
            'notes' => $this->notes,
            'cancel_reason' => $this->cancel_reason,
            'queued_at' => $this->queued_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'timeline' => $this->timeline ?? [],
        ];
    }
}
