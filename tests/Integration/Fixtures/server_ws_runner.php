<?php

declare(strict_types=1);

/**
 * Integration test server runner for WebSocket.
 *
 * Boots a REAL SwooleServer whose handler implements WebSocketHandlerInterface, so the adapter
 * stands up a Swoole\WebSocket\Server. Accepts upgrades on /ws and echoes each text message back
 * as "echo:<message>". Launched as a separate process by ServerWebSocketTest; the port is argv[1].
 */

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Contracts\Server\WebSocketHandlerInterface;
use PHPdot\Server\Swoole\Config\ServerConfig;
use PHPdot\Server\Swoole\SwooleServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/../../../vendor/autoload.php';

$port = (int) ($argv[1] ?? 0);
if ($port <= 0) {
    fwrite(STDERR, "usage: server_ws_runner.php <port>\n");
    exit(1);
}

$factory = new Psr17Factory();

$config = new ServerConfig(
    host: '127.0.0.1',
    port: $port,
    workerNum: 1,
    logLevel: 5,
    hookFlags: 0,
);

$server = new SwooleServer($factory, $config);

$handler = new class ($factory, $server) implements RequestHandlerInterface, WebSocketHandlerInterface {
    public function __construct(
        private readonly Psr17Factory $factory,
        private readonly SwooleServer $server,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->factory->createStream('HTTP-OK'));
    }

    public function handleWsOpen(
        int $fd,
        ServerRequestInterface $request,
        Closure $send,
        Closure $sendBinary,
        Closure $close,
    ): bool {
        return $request->getUri()->getPath() === '/ws';
    }

    public function handleWsMessage(int $fd, string $data, int $opcode): void
    {
        $this->server->push($fd, 'echo:' . $data);
    }

    public function handleWsClose(int $fd, int $code, string $reason): void {}
};

$server->onStart(static function (): void {
    echo "READY\n";
});
$server->serve($handler);
