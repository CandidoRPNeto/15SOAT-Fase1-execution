<?php

return [
    // Max active (queued + in_progress) tasks before new work is refused and
    // the saga compensates. Set to 0 to demo enqueue failure + compensation.
    'queue_capacity' => (int) env('EXECUTION_QUEUE_CAPACITY', 50),
];
