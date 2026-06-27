<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnAfterReloadInterface.
 *
 * Lifecycle listener invoked on the master just after workers have reloaded.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnAfterReloadInterface
{
    public function onAfterReload(SwooleServer $server): void;
}
