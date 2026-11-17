<?php

namespace App\Providers;

use App\Application\Execution\EnqueueTask;
use App\Application\Execution\ExecutionEventEmitter;
use App\Application\Execution\ExecutionMessageRouter;
use App\Application\Ports\MessageConsumer;
use App\Application\Ports\MessagePublisher;
use App\Application\Ports\MessageRouter;
use App\Application\Ports\TraceContext;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqConnectionFactory;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessageConsumer;
use App\Infrastructure\Messaging\RabbitMq\RabbitMqMessagePublisher;
use App\Infrastructure\Tracing\DatadogTraceContext;
use App\Infrastructure\Tracing\NullTraceContext;
use App\Infrastructure\Tracing\ServeCommandTracerEnv;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RabbitMqConnectionFactory::class, fn () => new RabbitMqConnectionFactory(config('messaging.rabbitmq')));
        $this->app->bind(MessageRouter::class, ExecutionMessageRouter::class);
        $this->app->singleton(MessagePublisher::class, RabbitMqMessagePublisher::class);
        $this->app->singleton(MessageConsumer::class, RabbitMqMessageConsumer::class);
        $this->app->singleton(TraceContext::class, fn () => DatadogTraceContext::available() ? new DatadogTraceContext : new NullTraceContext);
        $this->app->bind(EnqueueTask::class, fn () => new EnqueueTask(
            $this->app->make(ExecutionEventEmitter::class),
            (int) config('execution.queue_capacity'),
        ));
    }

    public function boot(): void
    {
        ServeCommandTracerEnv::passThrough();
    }
}
