<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnManagerStopInterface.
 *
 * Lifecycle listener invoked when the manager process is stopping.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnManagerStopInterface
{
    public function onManagerStop(SwooleServer $server): void;
}
