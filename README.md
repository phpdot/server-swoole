# phpdot/server-swoole

Swoole HTTP/WebSocket server adapter for PSR-15. Framework-agnostic, standalone, full Swoole coverage.

## Install

```bash
composer require phpdot/server-swoole
```

Requires `ext-swoole >= 6.0` and PHP 8.3+.

## Quick Start

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Server\Swoole\SwooleServer;
use PHPdot\Server\Swoole\Config\ServerConfig;

$factory = new Psr17Factory();
$config = new ServerConfig(workerNum: 4);

$server = new SwooleServer($factory, $config);
$server->serve($handler, '0.0.0.0', 8080);
```

`$handler` is any PSR-15 `RequestHandlerInterface` -- your router, your framework, your middleware pipeline.

---

## Server Configuration

`ServerConfig` is a simple readonly data class. No static methods, no builders -- just named constructor parameters:

```php
$config = new ServerConfig(
    workerNum: 8,
    maxRequest: 50000,
    daemonize: true,
    pidFile: '/var/run/app.pid',
    logFile: '/var/log/app.log',
);
```

### Workers & Process

```php
$config = new ServerConfig(
    workerNum: 8,              // worker processes (default: CPU count)
    taskWorkerNum: 4,          // task workers (default: 0)
    maxRequest: 100000,        // restart worker after N requests
    maxCoroutine: 100000,      // max coroutines per worker
    mode: SWOOLE_PROCESS,      // SWOOLE_PROCESS (default) or SWOOLE_BASE
);
```

### SSL / HTTPS

```php
$config = new ServerConfig(
    sockType: SWOOLE_SOCK_TCP | SWOOLE_SSL,
    sslCertFile: '/etc/ssl/certs/app.pem',
    sslKeyFile: '/etc/ssl/private/app.key',
    http2: true,
);

$server->serve($handler, '0.0.0.0', 443);
```

### Static Files

```php
$config = new ServerConfig(
    staticHandler: true,
    documentRoot: '/var/www/public',
    staticHandlerLocations: ['/assets', '/images', '/favicon.ico'],
);
```

Static file requests bypass PHP entirely -- served directly by Swoole's kernel.

### Compression

```php
$config = new ServerConfig(
    httpCompression: true,          // enabled by default
    httpCompressionLevel: 3,        // 1-9 (default: 1)
    httpCompressionMinLength: 20,   // min bytes to compress (default: 20)
);
```

### Raw Swoole Settings

For any Swoole setting not covered by typed properties:

```php
$config = new ServerConfig(
    workerNum: 4,
    rawSettings: [
        'dispatch_mode' => 2,
        'reload_async' => true,
    ],
);
```

Typed properties always take precedence over `rawSettings`.

---

## Event Callbacks

Register callbacks directly on the server. Multiple callbacks per event -- they stack, never replace:

```php
$server = new SwooleServer($factory, $config);

// Lifecycle
$server->onStart(function (Server $server): void {
    cli_set_process_title('app: master');
});

$server->onWorkerStart(function (Server $server, int $workerId): void {
    cli_set_process_title("app: worker {$workerId}");
});

$server->onShutdown(function (Server $server): void {
    echo "Server stopped\n";
});
```

### Available Events

| Category | Events |
|----------|--------|
| Lifecycle | `onStart`, `onManagerStart`, `onManagerStop`, `onWorkerStart`, `onWorkerStop`, `onWorkerExit`, `onWorkerError`, `onBeforeShutdown`, `onShutdown`, `onBeforeReload`, `onAfterReload` |
| Connection | `onConnect`, `onClose` |
| Task | `onTask`, `onFinish` |
| IPC | `onPipeMessage` |
| WebSocket | `onOpen`, `onMessage`, `onHandshake`, `onDisconnect` |

---

## WebSocket

When any WebSocket callback is registered, the server automatically creates a `WebSocket\Server` instead of `Http\Server`. HTTP and WebSocket work on the same port:

```php
$server->onOpen(function (WebSocketServer $server, Request $request): void {
    echo "Client connected: {$request->fd}\n";
});

$server->onMessage(function (WebSocketServer $server, Frame $frame): void {
    $server->push($frame->fd, "Echo: {$frame->data}");
});

$server->onClose(function (Server $server, int $fd): void {
    echo "Client disconnected: {$fd}\n";
});

$server->serve($handler, '0.0.0.0', 8080);
```

### Active WebSocket Methods

Push messages and manage connections from anywhere in your application:

```php
$server->push($fd, $data);              // send data to a client
$server->wsDisconnect($fd);             // disconnect a client
$server->isEstablished($fd);            // check if connection is active
```

---

## Task Workers

Offload heavy work to task worker processes:

```php
$server = new SwooleServer($factory, new ServerConfig(taskWorkerNum: 4));

$server->onTask(function (Server $server, Task $task): void {
    // runs in a task worker process
    $result = processHeavyWork($task->data);
    $task->finish($result);
});

$server->onFinish(function (Server $server, int $taskId, mixed $data): void {
    // result returned to the requesting worker
});

// dispatch from anywhere after serve()
$server->task($data);                          // async dispatch
$server->taskCo([$data1, $data2], timeout: 1); // coroutine dispatch, wait for results
$server->finish($result);                      // return result from task worker
```

---

## Timers

Set recurring or one-shot timers:

```php
$timerId = $server->tick(5000, function (): void {
    // runs every 5 seconds
});

$server->after(10000, function (): void {
    // runs once after 10 seconds
});

$server->clearTimer($timerId);
```

---

## Connection Management

```php
$server->exists($fd);                     // check if connection exists
$server->close($fd);                      // close a connection
$server->getClientInfo($fd);              // get connection details
$server->getClientList();                 // list connected file descriptors
$server->sendMessage($data, $workerId);   // send message to another worker
```

---

## Server Info & Lifecycle

```php
// info
$server->stats();
$server->getWorkerId();
$server->getWorkerPid();
$server->getWorkerStatus();
$server->getMasterPid();
$server->getManagerPid();

// lifecycle
$server->shutdown();
$server->reload();
$server->stop($workerId);
```

---

## Escape Hatch

For advanced Swoole features not directly exposed (addProcess, addListener, bind, protect, etc.):

```php
$swoole = $server->getServer();
$swoole->addProcess(new Process(function () { /* ... */ }));
```

---

## Streaming (CallbackStreamInterface)

For real-time streaming (SSE, chunked responses), implement `CallbackStreamInterface`:

```php
use PHPdot\Server\Swoole\CallbackStreamInterface;

final class SseStream implements StreamInterface, CallbackStreamInterface
{
    public function __construct(private readonly Closure $producer) {}

    public function getCallback(): Closure
    {
        return function (Closure $write): void {
            ($this->producer)($write);
        };
    }
}
```

The `ResponseConverter` detects this interface and streams each chunk directly via `$swooleResponse->write()` -- data reaches the client immediately without buffering.

---

## Architecture

```
                     Swoole HTTP/WS Server
                           |
                    Swoole\Http\Request
                           |
                           v
               +-------------------------+
               |   RequestConverter       |
               |                         |
               |   Swoole -> PSR-7       |
               |   Headers, URI, body,   |
               |   cookies, files        |
               +-------------------------+
                           |
                  ServerRequestInterface
                           |
                           v
               +-------------------------+
               |   Your PSR-15 Handler   |
               |                         |
               |   Router, middleware,   |
               |   controllers -- your   |
               |   application logic     |
               +-------------------------+
                           |
                   ResponseInterface
                           |
                           v
               +-------------------------+
               |   ResponseConverter     |
               |                         |
               |   PSR-7 -> Swoole       |
               |   Headers, cookies,     |
               |   sendfile, chunked,    |
               |   streaming             |
               +-------------------------+
                           |
                   Swoole\Http\Response
                           |
                        Client
```

### Response Emission Strategies

The `ResponseConverter` selects the optimal strategy for each response:

| Strategy | When | How |
|----------|------|-----|
| CallbackStream | Body implements `CallbackStreamInterface` | `write()` per chunk -- true streaming |
| Sendfile | Body is a plain file stream | `sendfile()` -- zero-copy kernel transfer |
| Empty | Body size is 0 | `end()` -- no body |
| Chunked | Body exceeds chunk threshold (default 1 MB) | `write()` in chunks |
| Direct | Everything else | `end($body)` -- single write |

---

## Production Example

```php
$config = new ServerConfig(
    workerNum: 8,
    taskWorkerNum: 2,
    maxRequest: 100000,
    daemonize: true,
    pidFile: '/var/run/app.pid',
    logFile: '/var/log/app.log',
    logLevel: SWOOLE_LOG_WARNING,
    sockType: SWOOLE_SOCK_TCP | SWOOLE_SSL,
    sslCertFile: '/etc/ssl/certs/app.pem',
    sslKeyFile: '/etc/ssl/private/app.key',
    http2: true,
    httpCompression: true,
    staticHandler: true,
    documentRoot: '/var/www/public',
);

$server = new SwooleServer($factory, $config);

$server->onWorkerStart(function (Server $server, int $workerId): void {
    cli_set_process_title("app: worker {$workerId}");
});

$server->serve($handler, '0.0.0.0', 443);
```

---

## Package Structure

```
src/
  SwooleServer.php                Main entry point -- events, active methods, lifecycle
  CallbackStreamInterface.php     Streaming contract
  Config/
    ServerConfig.php              Readonly server configuration
  Converter/
    RequestConverter.php          Swoole -> PSR-7
    ResponseConverter.php         PSR-7 -> Swoole
  Exception/
    ServerException.php           Server errors
```

## PSR Standards

| PSR | Usage |
|-----|-------|
| PSR-7 | `ServerRequestInterface`, `ResponseInterface` -- the bridge format |
| PSR-15 | `RequestHandlerInterface` -- your application entry point |
| PSR-17 | All 4 factories -- builds PSR-7 objects from Swoole data |

## Development

```bash
composer test        # PHPUnit
composer analyse     # PHPStan level 10
composer cs-fix      # PHP-CS-Fixer
composer check       # All three
```

## License

MIT
