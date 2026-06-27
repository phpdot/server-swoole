<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnManagerStartInterface.
 *
 * Lifecycle listener invoked when the manager process has started.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnManagerStartInterface
{
    public function onManagerStart(SwooleServer $server): void;
}
