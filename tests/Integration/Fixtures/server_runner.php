<?php

declare(strict_types=1);

/**
 * Integration test server runner.
 *
 * Boots a REAL SwooleServer with a routing PSR-15 handler, so the integration
 * tests exercise the actual request callback + ResponseConverter on the wire.
 * Launched as a separate process by ServerHttpTest; the port is argv[1].
 *
 * Routes:
 *   GET|HEAD /head-body          -> 200 with a 21-byte body (HEAD must emit no body)
 *   GET      /stream-throw       -> body streams a chunk via CallbackStream, then throws
 *   GET      /throw-before-write -> throws before anything is written (not-started path)
 *   *                            -> 200 "OK"
 */

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Contracts\Server\SseHandlerInterface;
use PHPdot\Server\Swoole\Config\ServerConfig;
use PHPdot\Server\Swoole\Contract\CallbackStreamInterface;
use PHPdot\Server\Swoole\SwooleServer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;

require __DIR__ . '/../../../vendor/autoload.php';

$port = (int) ($argv[1] ?? 0);
if ($port <= 0) {
    fwrite(STDERR, "usage: server_runner.php <port>\n");
    exit(1);
}

$factory = new Psr17Factory();

/** A PSR-7 stream that also drives Swoole via a deferred callback: writes one chunk, then throws. */
$callbackStream = new class implements StreamInterface, CallbackStreamInterface {
    public function getCallback(): Closure
    {
        return static function (Closure $write): void {
            $write('PARTIAL-CHUNK-OK');
            throw new RuntimeException('BOOM_SECRET_42');
        };
    }

    public function __toString(): string
    {
        return '';
    }
    public function close(): void {}
    public function detach()
    {
        return null;
    }
    public function getSize(): ?int
    {
        return null;
    }
    public function tell(): int
    {
        return 0;
    }
    public function eof(): bool
    {
        return true;
    }
    public function isSeekable(): bool
    {
        return false;
    }
    public function seek(int $offset, int $whence = SEEK_SET): void {}
    public function rewind(): void {}
    public function isWritable(): bool
    {
        return false;
    }
    public function write(string $string): int
    {
        return 0;
    }
    public function isReadable(): bool
    {
        return false;
    }
    public function read(int $length): string
    {
        return '';
    }
    public function getContents(): string
    {
        return '';
    }
    public function getMetadata(?string $key = null)
    {
        return null;
    }
};

$handler = new class ($factory, $callbackStream) implements RequestHandlerInterface, SseHandlerInterface {
    public function __construct(
        private readonly Psr17Factory $factory,
        private readonly StreamInterface $callbackStream,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();

        if ($path === '/head-body') {
            return $this->text('HELLO-BODY-1234567890');
        }

        if ($path === '/stream-throw') {
            return $this->factory->createResponse(200)
                ->withHeader('Content-Type', 'text/plain')
                ->withBody($this->callbackStream);
        }

        if ($path === '/throw-before-write') {
            throw new RuntimeException('BOOM_SECRET_BEFORE');
        }

        if ($path === '/echo-body') {
            return $this->text($request->getBody()->getContents());
        }

        if ($path === '/echo-request') {
            $name = $request->getQueryParams()['name'] ?? '';
            $cookie = $request->getCookieParams()['sid'] ?? '';

            return $this->text(sprintf(
                'm=%s;q=%s;h=%s;c=%s',
                $request->getMethod(),
                is_string($name) ? $name : '',
                $request->getHeaderLine('X-Test'),
                is_string($cookie) ? $cookie : '',
            ));
        }

        if ($path === '/set-cookie') {
            return $this->text('cookie')->withHeader('Set-Cookie', 'sid=abc123; Path=/; HttpOnly');
        }

        if ($path === '/set-session-cookie') {
            return $this->text('session')->withHeader('Set-Cookie', 'sess=xyz; Path=/');
        }

        if ($path === '/large') {
            return $this->text(str_repeat('Z', 1_500_000));
        }

        if ($path === '/created') {
            return $this->factory->createResponse(201)
                ->withHeader('Content-Type', 'text/plain')
                ->withHeader('X-Request-Id', 'r-123')
                ->withBody($this->factory->createStream('made'));
        }

        return $this->text('OK');
    }

    public function handleSse(ServerRequestInterface $request, Closure $write, Closure $close): bool
    {
        if ($request->getUri()->getPath() !== '/sse') {
            return false;
        }

        $write("data: e1\n\n");
        $write("data: e2\n\n");
        $close();

        return true;
    }

    private function text(string $body): ResponseInterface
    {
        return $this->factory->createResponse(200)
            ->withHeader('Content-Type', 'text/plain')
            ->withBody($this->factory->createStream($body));
    }
};

$config = new ServerConfig(
    host: '127.0.0.1',
    port: $port,
    workerNum: 1,
    logLevel: 5,
    hookFlags: 0,
);

$server = new SwooleServer($factory, $config);
$server->onStart(static function (): void {
    echo "READY\n";
});
$server->serve($handler);
