<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class ProcessedMessage extends Model
{
    public $timestamps = false;

    protected $connection = 'mongodb';

    protected $table = 'processed_messages';

    protected $fillable = ['message_id', 'type', 'processed_at'];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime'];
    }
}
