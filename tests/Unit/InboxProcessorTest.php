<?php

namespace Tests\Unit;

use App\Application\Messaging\InboxProcessor;
use App\Application\Ports\MessageRouter;
use App\Domain\Messaging\MessageEnvelope;
use App\Models\ProcessedMessage;
use RuntimeException;
use Tests\TestCase;

class InboxProcessorTest extends TestCase
{
    private function router(bool $fail = false): MessageRouter
    {
        return new class($fail) implements MessageRouter
        {
            /** @var list<string> */
            public array $routed = [];

            public function __construct(private readonly bool $fail) {}

            public function bindings(): array
            {
                return ['evt.#'];
            }

            public function route(MessageEnvelope $envelope): void
            {
                if ($this->fail) {
                    throw new RuntimeException('handler failed');
                }
                $this->routed[] = $envelope->messageId;
            }
        };
    }

    public function test_routes_message_once_and_skips_redelivery(): void
    {
        $router = $this->router();
        $processor = new InboxProcessor($router);
        $envelope = MessageEnvelope::create('evt.x', 's', 'c', []);

        $this->assertTrue($processor->process($envelope));
        $this->assertFalse($processor->process($envelope));
        $this->assertSame([$envelope->messageId], $router->routed);
        $this->assertSame(1, ProcessedMessage::where('message_id', $envelope->messageId)->count());
    }

    public function test_failed_handler_does_not_mark_message_processed(): void
    {
        $processor = new InboxProcessor($this->router(fail: true));
        $envelope = MessageEnvelope::create('evt.x', 's', 'c', []);

        try {
            $processor->process($envelope);
            $this->fail('Expected handler exception');
        } catch (RuntimeException) {
            $this->assertSame(0, ProcessedMessage::where('message_id', $envelope->messageId)->count());
        }
    }
}
