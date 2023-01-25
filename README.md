# OpenTelemetry Revolt adapter

Propagates the current [`open-telemetry/context`](https://github.com/opentelemetry-php/context) to [`revolt/event-loop`](https://github.com/revoltphp/event-loop) callbacks.

```php
$context = Context::getCurrent();
EventLoop::queue(fn() => assert($context === Context::getCurrent()));
```

## Installation

```shell
composer require tbachert/otel-async-revolt-adapter
```

## Usage

The adapter is automatically injected for the global event loop.  
If you use a local event loop or set a new driver using `EventLoop::setDriver()` you must wrap the driver manually using `RevoltDriver::wrap()`.
