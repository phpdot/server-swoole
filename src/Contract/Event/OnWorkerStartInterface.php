<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnWorkerStartInterface.
 *
 * Lifecycle listener invoked when a worker starts — including after a reload,
 * since reloaded workers re-fork. The place for per-(re)start setup such as
 * opcache_reset() or cache warming.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnWorkerStartInterface
{
    public function onWorkerStart(SwooleServer $server, int $workerId): void;
}
