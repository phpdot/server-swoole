<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Exception;

use RuntimeException;

/**
 * ServerException.
 *
 * General-purpose exception for Swoole server errors.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ServerException extends RuntimeException {}
