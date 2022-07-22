<?php

declare(strict_types=1);

namespace OpenTelemetry\Async\Revolt;

use function assert;
use Closure;
use Fiber;
use OpenTelemetry\Context\Context;
use Revolt\EventLoop\Driver;
use Revolt\EventLoop\Suspension;
use function spl_object_id;
use Throwable;
use WeakReference;

final class RevoltDriver implements Driver
{
    /**
     * @var array<int, WeakReference<self>>
     */
    private static array $drivers = [];

    private readonly Driver $driver;
    private readonly Closure $errorCallback;

    /**
     * @var Closure(Throwable): void|null
     */
    private ?Closure $errorHandler = null;

    private function __construct(Driver $driver)
    {
        $this->driver = $driver;
        $this->errorCallback = static fn (Closure $errorHandler, Throwable $exception): mixed => $errorHandler($exception);

        $this->setErrorHandler($driver->getErrorHandler());
    }

    public function __destruct()
    {
        unset(self::$drivers[spl_object_id($this->driver)]);

        $this->driver->setErrorHandler($this->getErrorHandler());
    }

    /**
     * Wraps the given driver in a context aware driver.
     *
     * Using the original driver directly is undefined behavior.
     *
     * @param Driver $driver driver to wrap
     * @return Driver wrapped driver
     */
    public static function wrap(
        Driver $driver,
    ): Driver {
        if ($driver instanceof self) {
            return $driver;
        }

        if ($reference = self::$drivers[spl_object_id($driver)] ?? null) {
            $revoltDriver = $reference->get();
            assert($revoltDriver instanceof self);

            return $revoltDriver;
        }

        $revoltDriver = new self($driver);
        self::$drivers[spl_object_id($driver)] = WeakReference::create($revoltDriver);

        return $revoltDriver;
    }

    /**
     * @psalm-template T of Closure
     * @psalm-param T $closure
     * @psalm-return T
     *
     * @psalm-suppress InvalidReturnType
     * @psalm-suppress InvalidReturnStatement
     */
    private function bindContext(Closure $closure): Closure
    {
        $context = Context::getCurrent();

        return function (mixed ...$args) use ($closure, $context): mixed {
            $scope = $context->activate();

            try {
                return $closure(...$args);
            } catch (Throwable $exception) {
                if (!$this->errorHandler) {
                    throw $exception;
                }

                $fiber = new Fiber($this->errorCallback);
                /** @psalm-suppress PossiblyUndefinedVariable */
                $fiber->start($this->errorHandler, $exception);

                return null;
            } finally {
                $scope->detach();
            }
        };
    }

    public function run(): void
    {
        $this->driver->run();
    }

    public function stop(): void
    {
        $this->driver->stop();
    }

    public function getSuspension(): Suspension
    {
        return $this->driver->getSuspension();
    }

    public function isRunning(): bool
    {
        return $this->driver->isRunning();
    }

    public function queue(Closure $closure, mixed ...$args): void
    {
        $this->driver->queue($this->bindContext($closure), ...$args);
    }

    public function defer(Closure $closure): string
    {
        return $this->driver->defer($this->bindContext($closure));
    }

    public function delay(float $delay, Closure $closure): string
    {
        return $this->driver->delay($delay, $this->bindContext($closure));
    }

    public function repeat(float $interval, Closure $closure): string
    {
        return $this->driver->repeat($interval, $this->bindContext($closure));
    }

    public function onReadable(mixed $stream, Closure $closure): string
    {
        return $this->driver->onReadable($stream, $this->bindContext($closure));
    }

    public function onWritable(mixed $stream, Closure $closure): string
    {
        return $this->driver->onWritable($stream, $this->bindContext($closure));
    }

    public function onSignal(int $signal, Closure $closure): string
    {
        return $this->driver->onSignal($signal, $this->bindContext($closure));
    }

    public function enable(string $callbackId): string
    {
        return $this->driver->enable($callbackId);
    }

    public function cancel(string $callbackId): void
    {
        $this->driver->cancel($callbackId);
    }

    public function disable(string $callbackId): string
    {
        return $this->driver->disable($callbackId);
    }

    public function reference(string $callbackId): string
    {
        return $this->driver->reference($callbackId);
    }

    public function unreference(string $callbackId): string
    {
        return $this->driver->unreference($callbackId);
    }

    public function setErrorHandler(?Closure $errorHandler): void
    {
        if ($errorHandler) {
            $this->errorHandler = $errorHandler;
            $this->driver->setErrorHandler(static fn (Throwable $exception) => throw $exception);
        } else {
            $this->errorHandler = null;
            $this->driver->setErrorHandler(null);
        }
    }

    public function getErrorHandler(): ?Closure
    {
        return $this->errorHandler;
    }

    public function getHandle(): mixed
    {
        return $this->driver->getHandle();
    }

    public function __debugInfo(): array
    {
        return $this->driver->__debugInfo();
    }

    public function getInfo(): array
    {
        return $this->driver->getInfo();
    }
}
