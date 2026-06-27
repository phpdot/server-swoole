<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Contract\Event;

use PHPdot\Server\Swoole\SwooleServer;

/**
 * OnBeforeReloadInterface.
 *
 * Lifecycle listener invoked on the master just before workers are reloaded.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
interface OnBeforeReloadInterface
{
    public function onBeforeReload(SwooleServer $server): void;
}
