<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole;

use Closure;

/**
 * CallbackStreamInterface.
 *
 * Interface for streams that emit their content via a deferred callback,
 * enabling chunked or streamed responses through Swoole.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface CallbackStreamInterface
{
    /**
     * Get the deferred callback for streaming.
     *
     * The callback receives a write function: fn(string $chunk): void
     *
     * @return Closure(Closure(string): void): void
     */
    public function getCallback(): Closure;
}
