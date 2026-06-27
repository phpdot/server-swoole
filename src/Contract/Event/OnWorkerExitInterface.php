<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnWorkerExitInterface.
 *
 * Lifecycle listener invoked while a worker is exiting (only with reload_async),
 * once per loop until the worker leaves its event loop.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnWorkerExitInterface
{
    public function onWorkerExit(SwooleServer $server, int $workerId): void;
}
