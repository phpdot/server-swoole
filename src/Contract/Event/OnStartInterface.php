<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnStartInterface.
 *
 * Lifecycle listener invoked once when the master process has started.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnStartInterface
{
    public function onStart(SwooleServer $server): void;
}
