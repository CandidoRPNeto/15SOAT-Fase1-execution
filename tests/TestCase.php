<?php

namespace Tests;

use App\Models\ExecutionTask;
use App\Models\OutboxMessage;
use App\Models\ProcessedMessage;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Tests run against a real MongoDB replica set (MONGODB_URI; CI service
 * container). Collections are emptied before each test; indexes are created
 * once per process by the migration.
 */
abstract class TestCase extends BaseTestCase
{
    private static bool $migrated = false;

    protected function setUp(): void
    {
        parent::setUp();

        if (! self::$migrated) {
            Artisan::call('migrate:fresh', ['--force' => true]);
            // migrate:fresh disconnects the MongoDB client; transactions
            // (InboxProcessor) would then fail with "startSession() on null".
            DB::purge('mongodb');
            self::$migrated = true;
        }

        ExecutionTask::truncate();
        OutboxMessage::truncate();
        ProcessedMessage::truncate();
    }
}
