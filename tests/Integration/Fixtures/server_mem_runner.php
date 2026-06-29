<?php

declare(strict_types=1);

/**
 * Integration test server runner for the memory-regression test.
 *
 * Pins the worker (maxRequest:0 — never recycle, which would reset the heap and hide a leak) and
 * exposes /mem, where the worker self-reports its current emalloc heap. /ok is the load target.
 * Launched as a separate process by ServerMemoryTest; the port is argv[1].
 */

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Server\Swoole\Config\ServerConfig;
use PHPdot\Server\Swoole\SwooleServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/../../../vendor/autoload.php';

$port = (int) ($argv[1] ?? 0);
if ($port <= 0) {
    fwrite(STDERR, "usage: server_mem_runner.php <port>\n");
    exit(1);
}

$factory = new Psr17Factory();

$config = new ServerConfig(
    host: '127.0.0.1',
    port: $port,
    workerNum: 1,
    maxRequest: 0,
    logLevel: 5,
    hookFlags: 0,
);

$server = new SwooleServer($factory, $config);

$handler = new class ($factory) implements RequestHandlerInterface {
    public function __construct(private readonly Psr17Factory $factory) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getUri()->getPath() === '/mem') {
            $payload = json_encode(['heap' => memory_get_usage(false)]);

            return $this->factory->createResponse(200)
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->factory->createStream($payload !== false ? $payload : '{}'));
        }

        return $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->factory->createStream('OK'));
    }
};

$server->onStart(static function (): void {
    echo "READY\n";
});
$server->serve($handler);
