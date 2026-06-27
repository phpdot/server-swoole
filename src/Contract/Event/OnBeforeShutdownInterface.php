<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnBeforeShutdownInterface.
 *
 * Lifecycle listener invoked on the master just before the server shuts down,
 * ahead of onShutdown.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnBeforeShutdownInterface
{
    public function onBeforeShutdown(SwooleServer $server): void;
}
