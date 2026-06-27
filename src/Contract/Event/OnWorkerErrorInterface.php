<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnWorkerErrorInterface.
 *
 * Lifecycle listener invoked on the manager when a worker exits abnormally
 * (fatal error or signal). The place to alert or record a crash.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnWorkerErrorInterface
{
    public function onWorkerError(SwooleServer $server, int $workerId, int $workerPid, int $exitCode, int $signal): void;
}
