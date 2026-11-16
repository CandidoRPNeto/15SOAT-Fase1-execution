<?php

namespace App\Domain\Execution;

/**
 * Routing keys the Execution Service consumes (cmd.execution.*) and produces
 * (evt.execution.*). Contract: docs/asyncapi.yaml.
 */
enum ExecutionMessage: string
{
    case ENQUEUE_DIAGNOSIS = 'cmd.execution.enqueue_diagnosis';
    case ENQUEUE_REPAIR = 'cmd.execution.enqueue_repair';
    case CANCEL_TASK = 'cmd.execution.cancel_task';

    case DIAGNOSIS_ENQUEUED = 'evt.execution.diagnosis_enqueued';
    case DIAGNOSIS_ENQUEUE_FAILED = 'evt.execution.diagnosis_enqueue_failed';
    case DIAGNOSIS_STARTED = 'evt.execution.diagnosis_started';
    case DIAGNOSIS_COMPLETED = 'evt.execution.diagnosis_completed';
    case REPAIR_ENQUEUED = 'evt.execution.repair_enqueued';
    case REPAIR_ENQUEUE_FAILED = 'evt.execution.repair_enqueue_failed';
    case REPAIR_STARTED = 'evt.execution.repair_started';
    case REPAIR_FINISHED = 'evt.execution.repair_finished';
}
