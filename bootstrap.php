<?php

declare(strict_types=1);

use Nevay\Otel\Async\Revolt\RevoltDriver;
use OpenTelemetry\Context\ZendObserverFiber;
use React\EventLoop\Loop;
use Revolt\EventLoop;
use Revolt\EventLoop\React\Internal\EventLoopAdapter;

EventLoop::setDriver(RevoltDriver::wrap(EventLoop::getDriver()));

// If we load after revolt/event-loop-adapter-react
if (class_exists(EventLoopAdapter::class, false)) {
    /**
     * @psalm-suppress InternalMethod
     * @phan-suppress-next-next-line PhanAccessMethodInternal
     */
    Loop::set(EventLoopAdapter::get());
}

try {
    // Force enable fiber support to not require OTEL_PHP_FIBERS_ENABLED
    @ZendObserverFiber::init();
} catch (Throwable) {}
