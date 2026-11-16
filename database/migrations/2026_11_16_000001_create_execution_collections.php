<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use MongoDB\Laravel\Schema\Blueprint;

/**
 * Collections and indexes (ADR-012). Unique message_id enforces inbox
 * idempotency even under concurrent consumers.
 */
return new class extends Migration
{
    protected $connection = 'mongodb';

    public function up(): void
    {
        Schema::create('execution_tasks', function (Blueprint $collection) {
            $collection->unique('service_order_id');
            $collection->index(['status' => 1, 'priority' => -1, 'queued_at' => 1]);
        });
        Schema::create('outbox_messages', function (Blueprint $collection) {
            $collection->unique('message_id');
            $collection->index('published_at');
        });
        Schema::create('processed_messages', function (Blueprint $collection) {
            $collection->unique('message_id');
        });
    }

    public function down(): void
    {
        Schema::drop('processed_messages');
        Schema::drop('outbox_messages');
        Schema::drop('execution_tasks');
    }
};
