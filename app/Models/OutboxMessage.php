<?php

namespace App\Models;

use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Outbox document (ADR-011/012). Ordered by _id (ObjectId is monotonic per
 * process), which is what OutboxRelay's orderBy('id') resolves to.
 *
 * @property string $message_id
 * @property string $type
 * @property array<string, mixed> $body
 * @property array<string, string>|null $trace_context
 * @property int $attempts
 * @property Carbon|null $published_at
 */
class OutboxMessage extends Model
{
    protected $connection = 'mongodb';

    protected $table = 'outbox_messages';

    protected $fillable = ['message_id', 'type', 'body', 'trace_context', 'attempts', 'published_at'];

    protected $attributes = ['attempts' => 0, 'published_at' => null];

    protected function casts(): array
    {
        return ['published_at' => 'datetime'];
    }
}
