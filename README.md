# phpdot/server-swoole

Swoole HTTP server adapter for PSR-15. Framework-agnostic.

## Install

```bash
composer require phpdot/server-swoole
```

Requires the Swoole extension (`ext-swoole >= 6.0`).

## Quick Start

```php
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Server\Swoole\SwooleServer;

$psr17 = new Psr17Factory();
$server = SwooleServer::withPsr17($psr17);
$server->serve($handler, '0.0.0.0', 8080);
```

`$handler` is any PSR-15 `RequestHandlerInterface` — your router, your framework, your middleware pipeline.

---

## Architecture

```
                     Swoole HTTP Server
                           │
                    Swoole\Http\Request
                           │
                           ▼
               ┌───────────────────────┐
               │   RequestConverter     │
               │                       │
               │   Swoole → PSR-7      │
               │   Headers, URI, body, │
               │   cookies, files      │
               └───────────────────────┘
                           │
                  ServerRequestInterface
                           │
                           ▼
               ┌───────────────────────┐
               │   Your PSR-15 Handler  │
               │                       │
               │   Router, middleware, │
               │   controllers — your  │
               │   application logic   │
               └───────────────────────┘
                           │
                   ResponseInterface
                           │
                           ▼
               ┌───────────────────────┐
               │   ResponseConverter    │
               │                       │
               │   PSR-7 → Swoole      │
               │   Headers, cookies,   │
               │   sendfile, chunked,  │
               │   streaming           │
               └───────────────────────┘
                           │
                   Swoole\Http\Response
                           │
                        Client
```

### Response Emission Strategies

The ResponseConverter selects the optimal strategy for each response:

| Strategy | When | How |
|----------|------|-----|
| **CallbackStream** | Body implements `CallbackStreamInterface` | `$swooleResponse->write()` per chunk — true streaming |
| **Sendfile** | Body is a plain file stream | `$swooleResponse->sendfile()` — zero-copy kernel transfer |
| **Empty** | Body size is 0 | `$swooleResponse->end()` — no body |
| **Chunked** | Body exceeds chunk threshold (default 1MB) | `$swooleResponse->write()` in chunks |
| **Direct** | Everything else | `$swooleResponse->end($body)` — single write |

---

## Server Configuration

```php
use PHPdot\Server\Swoole\Config\ServerConfig;

$config = ServerConfig::default()
    ->withWorkerNum(8)
    ->withMaxRequest(50000)
    ->withDaemonize(true)
    ->withPidFile('/var/run/app.pid')
    ->withLogFile('/var/log/app.log');

$server->serve($handler, '0.0.0.0', 8080, $config);
```

### Workers & Process

```php
$config = ServerConfig::default()
    ->withWorkerNum(8)                 // worker processes (default: CPU count)
    ->withTaskWorkerNum(4)             // task workers (default: 0)
    ->withMaxRequest(100000)           // restart worker after N requests
    ->withMaxCoroutine(100000)         // max coroutines per worker
    ->withMode(SWOOLE_BASE);           // SWOOLE_PROCESS (default) or SWOOLE_BASE
```

### SSL / HTTPS

```php
$config = ServerConfig::default()
    ->withSockType(SWOOLE_SOCK_TCP | SWOOLE_SSL)
    ->withSslCertFile('/etc/ssl/certs/app.pem')
    ->withSslKeyFile('/etc/ssl/private/app.key')
    ->withHttp2(true);

$server->serve($handler, '0.0.0.0', 443, $config);
```

### Static Files

```php
$config = ServerConfig::default()
    ->withStaticHandler(true)
    ->withDocumentRoot('/var/www/public')
    ->withStaticHandlerLocations(['/assets', '/images', '/favicon.ico']);
```

Static file requests bypass PHP entirely — served directly by Swoole's kernel.

### Compression

```php
$config = ServerConfig::default()
    ->withHttpCompression(true)        // enabled by default
    ->withHttpCompressionLevel(3)      // 1-9 (default: 1)
    ->withHttpCompressionMinLength(20);// min bytes to compress (default: 20)
```

### Lifecycle Hooks

```php
$config = ServerConfig::default()
    ->onStart(function ($server): void {
        cli_set_process_title('app: master');
    })
    ->onWorkerStart(function ($server, int $workerId): void {
        cli_set_process_title("app: worker {$workerId}");
    })
    ->onShutdown(function ($server): void {
        echo "Server stopped\n";
    });
```

Available hooks: `onStart`, `onManagerStart`, `onWorkerStart`, `onWorkerStop`, `onWorkerExit`, `onWorkerError`, `onBeforeShutdown`, `onShutdown`.

### Production Example

```php
$config = ServerConfig::default()
    ->withWorkerNum(swoole_cpu_num() * 2)
    ->withMaxRequest(100000)
    ->withDaemonize(true)
    ->withPidFile('/var/run/app.pid')
    ->withLogFile('/var/log/app.log')
    ->withLogLevel(SWOOLE_LOG_WARNING)
    ->withSockType(SWOOLE_SOCK_TCP | SWOOLE_SSL)
    ->withSslCertFile('/etc/ssl/certs/app.pem')
    ->withSslKeyFile('/etc/ssl/private/app.key')
    ->withHttp2(true)
    ->withHttpCompression(true)
    ->withStaticHandler(true)
    ->withDocumentRoot('/var/www/public')
    ->onStart(function ($server): void {
        cli_set_process_title('app: master');
    })
    ->onWorkerStart(function ($server, int $workerId): void {
        cli_set_process_title("app: worker {$workerId}");
    });
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

The ResponseConverter detects this interface and streams each chunk directly via `$swooleResponse->write()` — data reaches the client immediately without buffering.

---

## Framework Examples

### With phpdot/routing

```php
$router = new Router($container, $psr17);
$router->get('/health', fn($req) => $factory->json(['ok' => true]));
$router->compile();

$server = SwooleServer::withPsr17($psr17);
$server->serve($router, '0.0.0.0', 8080);
```

### With Slim

```php
$app = AppFactory::create();
$app->get('/hello', function ($req, $res) {
    $res->getBody()->write('Hello');
    return $res;
});

$server = SwooleServer::withPsr17(new Psr17Factory());
$server->serve($app, '0.0.0.0', 8080);
```

### With Mezzio

```php
$app = $container->get(Application::class);

$server = SwooleServer::withPsr17(new Psr17Factory());
$server->serve($app, '0.0.0.0', 8080);
```

---

## Package Structure

```
src/
├── SwooleServer.php                Main entry point
├── CallbackStreamInterface.php     Streaming contract
├── Config/
│   └── ServerConfig.php            Immutable server configuration
├── Converter/
│   ├── RequestConverter.php        Swoole → PSR-7
│   └── ResponseConverter.php       PSR-7 → Swoole
└── Exception/
    └── ServerException.php         Configuration errors
```

---

## PSR Standards

| PSR | Usage |
|-----|-------|
| PSR-7 | `ServerRequestInterface`, `ResponseInterface` — the bridge format |
| PSR-15 | `RequestHandlerInterface` — your application entry point |
| PSR-17 | All 4 factories — builds PSR-7 objects from Swoole data |

---

## Development

```bash
composer test        # PHPUnit (63 tests)
composer analyse     # PHPStan level 10
composer cs-fix      # PHP-CS-Fixer
composer check       # All three
```

## License

MIT
