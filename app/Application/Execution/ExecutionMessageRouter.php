<?php

namespace App\Application\Execution;

use App\Application\Ports\MessageRouter;
use App\Domain\Execution\ExecutionMessage;
use App\Domain\Execution\ExecutionPhase;
use App\Domain\Messaging\MessageEnvelope;
use InvalidArgumentException;

/**
 * Routes saga commands addressed to Execution.
 */
class ExecutionMessageRouter implements MessageRouter
{
    public function __construct(
        private readonly EnqueueTask $enqueue,
        private readonly CancelTask $cancel,
    ) {}

    public function bindings(): array
    {
        return ['cmd.execution.*'];
    }

    public function route(MessageEnvelope $envelope): void
    {
        match (ExecutionMessage::tryFrom($envelope->type)) {
            ExecutionMessage::ENQUEUE_DIAGNOSIS => $this->enqueue->handle($envelope, ExecutionPhase::DIAGNOSIS),
            ExecutionMessage::ENQUEUE_REPAIR => $this->enqueue->handle($envelope, ExecutionPhase::REPAIR),
            ExecutionMessage::CANCEL_TASK => $this->cancel->handle($envelope),
            default => throw new InvalidArgumentException("Unsupported message type '{$envelope->type}'; expected one of cmd.execution.enqueue_diagnosis|enqueue_repair|cancel_task"),
        };
    }
}
