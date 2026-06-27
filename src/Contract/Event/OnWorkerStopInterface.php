<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnWorkerStopInterface.
 *
 * Lifecycle listener invoked when a worker is stopping — the place for graceful
 * per-worker teardown (flush buffers, close handles).
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnWorkerStopInterface
{
    public function onWorkerStop(SwooleServer $server, int $workerId): void;
}
