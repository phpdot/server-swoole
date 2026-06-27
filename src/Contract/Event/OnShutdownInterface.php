<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnShutdownInterface.
 *
 * Lifecycle listener invoked once when the master process is shutting down.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnShutdownInterface
{
    public function onShutdown(SwooleServer $server): void;
}
