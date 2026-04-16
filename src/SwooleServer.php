<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole;

use Closure;
use PHPdot\Contracts\Server\SseHandlerInterface;
use PHPdot\Contracts\Server\WebSocketHandlerInterface;
use PHPdot\Server\Swoole\Config\ServerConfig;
use PHPdot\Server\Swoole\Converter\RequestConverter;
use PHPdot\Server\Swoole\Converter\ResponseConverter;
use PHPdot\Server\Swoole\Exception\ServerException;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server;
use Swoole\Timer;
use Swoole\WebSocket\Frame as SwooleFrame;
use Swoole\WebSocket\Server as WebSocketServer;

/**
 * SwooleServer.
 *
 * Framework-agnostic Swoole HTTP/WebSocket server that bridges PSR-15
 * request handlers with the Swoole event loop. Provides typed access
 * to all Swoole server features: event callbacks, WebSocket push,
 * task dispatch, timers, and server lifecycle management.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class SwooleServer
{
    /** @var RequestConverter Converts Swoole requests to PSR-7 */
    private readonly RequestConverter $requestConverter;

    /** @var ResponseConverter Converts PSR-7 responses to Swoole */
    private readonly ResponseConverter $responseConverter;

    /** @var Server|null The running Swoole server instance */
    private Server|null $server = null;

    /** @var list<Closure> */
    private array $onStartCallbacks = [];

    /** @var list<Closure> */
    private array $onManagerStartCallbacks = [];

    /** @var list<Closure> */
    private array $onManagerStopCallbacks = [];

    /** @var list<Closure> */
    private array $onWorkerStartCallbacks = [];

    /** @var list<Closure> */
    private array $onWorkerStopCallbacks = [];

    /** @var list<Closure> */
    private array $onWorkerExitCallbacks = [];

    /** @var list<Closure> */
    private array $onWorkerErrorCallbacks = [];

    /** @var list<Closure> */
    private array $onBeforeShutdownCallbacks = [];

    /** @var list<Closure> */
    private array $onShutdownCallbacks = [];

    /** @var list<Closure> */
    private array $onBeforeReloadCallbacks = [];

    /** @var list<Closure> */
    private array $onAfterReloadCallbacks = [];

    /** @var list<Closure> */
    private array $onConnectCallbacks = [];

    /** @var list<Closure> */
    private array $onCloseCallbacks = [];

    /** @var list<Closure> */
    private array $onTaskCallbacks = [];

    /** @var list<Closure> */
    private array $onFinishCallbacks = [];

    /** @var list<Closure> */
    private array $onPipeMessageCallbacks = [];

    /** @var list<Closure> */
    private array $onOpenCallbacks = [];

    /** @var list<Closure> */
    private array $onMessageCallbacks = [];

    /** @var list<Closure> */
    private array $onHandshakeCallbacks = [];

    /** @var list<Closure> */
    private array $onDisconnectCallbacks = [];

    /**
     * Create a new SwooleServer.
     *
     * @param ServerRequestFactoryInterface&UriFactoryInterface&StreamFactoryInterface&UploadedFileFactoryInterface $httpFactory Combined PSR-17 factory
     * @param ServerConfig $config Server configuration
     */
    public function __construct(
        ServerRequestFactoryInterface&UriFactoryInterface&StreamFactoryInterface&UploadedFileFactoryInterface $httpFactory,
        private readonly ServerConfig $config = new ServerConfig(),
    ) {
        $this->requestConverter = new RequestConverter(
            $httpFactory,
            $httpFactory,
            $httpFactory,
            $httpFactory,
        );
        $this->responseConverter = new ResponseConverter();
    }

    /**
     * Start the Swoole server and begin handling requests.
     *
     * Automatically uses WebSocket\Server when WebSocket callbacks are
     * registered. This method blocks until the server is shut down.
     *
     * @param RequestHandlerInterface $handler PSR-15 request handler
     * @param string $host Host address to bind to
     * @param int $port Port number to listen on
     * @throws ServerException If WebSocket callbacks are registered without onMessage
     */
    public function serve(
        RequestHandlerInterface $handler,
        string $host = '0.0.0.0',
        int $port = 8080,
    ): void {
        $hasWsInterface = $handler instanceof WebSocketHandlerInterface;
        $hasSseInterface = $handler instanceof SseHandlerInterface;

        if ($this->hasWebSocket() && $this->onMessageCallbacks === [] && !$hasWsInterface) {
            throw new ServerException(
                'WebSocket callbacks registered but onMessage is missing. Swoole requires onMessage for WebSocket servers.',
            );
        }

        if ($hasWsInterface) {
            $this->registerWsHandler($handler);
        }

        $server = $this->createServer($host, $port);
        $server->set($this->config->toArray());

        $this->registerCallbacks($server);

        $server->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) use ($handler, $hasSseInterface): void {
            try {
                $psrRequest = $this->requestConverter->toServerRequest($swooleRequest);

                if ($hasSseInterface && str_contains($psrRequest->getHeaderLine('accept'), 'text/event-stream')) {
                    assert($handler instanceof SseHandlerInterface);

                    $swooleResponse->header('Content-Type', 'text/event-stream');
                    $swooleResponse->header('Cache-Control', 'no-cache');
                    $swooleResponse->header('Connection', 'keep-alive');

                    $handled = $handler->handleSse(
                        $psrRequest,
                        static function (string $data) use ($swooleResponse): void {
                            $swooleResponse->write($data);
                        },
                        static function () use ($swooleResponse): void {
                            $swooleResponse->end();
                        },
                    );

                    if ($handled) {
                        if (!$swooleResponse->isWritable()) {
                            return;
                        }
                        $swooleResponse->end();
                        return;
                    }
                }

                $psrResponse = $handler->handle($psrRequest);
                $this->responseConverter->toSwoole($psrResponse, $swooleResponse);
            } catch (\Throwable $e) {
                $swooleResponse->status(500);
                $swooleResponse->end($e->getMessage());
            }
        });

        $this->server = $server;
        $server->start();
    }

    /**
     * Register WebSocket event handlers from a WebSocketHandlerInterface.
     */
    private function registerWsHandler(WebSocketHandlerInterface $handler): void
    {
        $converter = $this->requestConverter;

        $this->onOpenCallbacks[] = static function (WebSocketServer $ws, SwooleRequest $req) use ($handler, $converter): void {
            $psrRequest = $converter->toServerRequest($req);

            $accepted = $handler->handleWsOpen(
                $req->fd,
                $psrRequest,
                static fn(string $data): bool => $ws->push($req->fd, $data),
                static fn(string $data): bool => $ws->push($req->fd, $data, WEBSOCKET_OPCODE_BINARY),
                static fn(int $code, string $reason): bool => $ws->disconnect($req->fd, $code, $reason),
            );

            if (!$accepted) {
                $ws->disconnect($req->fd);
            }
        };

        $this->onMessageCallbacks[] = static function (WebSocketServer $ws, SwooleFrame $frame) use ($handler): void {
            $handler->handleWsMessage($frame->fd, $frame->data, $frame->opcode);
        };

        $this->onCloseCallbacks[] = static function (Server $server, int $fd) use ($handler): void {
            $handler->handleWsClose($fd, 1000, '');
        };
    }

    /**
     * Gracefully shut down the server.
     *
     * @throws ServerException If the server has not been started
     */
    public function shutdown(): bool
    {
        return $this->getServer()->shutdown();
    }

    /**
     * Reload all worker processes.
     *
     * @param bool $onlyReloadTaskWorker Only reload task workers
     * @throws ServerException If the server has not been started
     */
    public function reload(bool $onlyReloadTaskWorker = false): bool
    {
        return $this->getServer()->reload($onlyReloadTaskWorker);
    }

    /**
     * Stop a worker process.
     *
     * @param int $workerId Worker ID to stop (-1 for current)
     * @param bool $waitEvent Wait for events to complete
     * @throws ServerException If the server has not been started
     */
    public function stop(int $workerId = -1, bool $waitEvent = false): bool
    {
        return $this->getServer()->stop($workerId, $waitEvent);
    }

    /**
     * Register a callback for the master process start event.
     *
     * Signature: function(Server $server): void
     */
    public function onStart(Closure $callback): void
    {
        $this->onStartCallbacks[] = $callback;
    }

    /**
     * Register a callback for the manager process start event.
     *
     * Signature: function(Server $server): void
     */
    public function onManagerStart(Closure $callback): void
    {
        $this->onManagerStartCallbacks[] = $callback;
    }

    /**
     * Register a callback for the manager process stop event.
     *
     * Signature: function(Server $server): void
     */
    public function onManagerStop(Closure $callback): void
    {
        $this->onManagerStopCallbacks[] = $callback;
    }

    /**
     * Register a callback for the worker process start event.
     *
     * Signature: function(Server $server, int $workerId): void
     */
    public function onWorkerStart(Closure $callback): void
    {
        $this->onWorkerStartCallbacks[] = $callback;
    }

    /**
     * Register a callback for the worker process stop event.
     *
     * Signature: function(Server $server, int $workerId): void
     */
    public function onWorkerStop(Closure $callback): void
    {
        $this->onWorkerStopCallbacks[] = $callback;
    }

    /**
     * Register a callback for the worker process exit event.
     *
     * Signature: function(Server $server, int $workerId): void
     */
    public function onWorkerExit(Closure $callback): void
    {
        $this->onWorkerExitCallbacks[] = $callback;
    }

    /**
     * Register a callback for the worker process error event.
     *
     * Signature: function(Server $server, int $workerId, int $workerPid, int $exitCode, int $signal): void
     */
    public function onWorkerError(Closure $callback): void
    {
        $this->onWorkerErrorCallbacks[] = $callback;
    }

    /**
     * Register a callback for the before-shutdown event.
     *
     * Signature: function(Server $server): void
     */
    public function onBeforeShutdown(Closure $callback): void
    {
        $this->onBeforeShutdownCallbacks[] = $callback;
    }

    /**
     * Register a callback for the server shutdown event.
     *
     * Signature: function(Server $server): void
     */
    public function onShutdown(Closure $callback): void
    {
        $this->onShutdownCallbacks[] = $callback;
    }

    /**
     * Register a callback for the before-reload event.
     *
     * Signature: function(Server $server): void
     */
    public function onBeforeReload(Closure $callback): void
    {
        $this->onBeforeReloadCallbacks[] = $callback;
    }

    /**
     * Register a callback for the after-reload event.
     *
     * Signature: function(Server $server): void
     */
    public function onAfterReload(Closure $callback): void
    {
        $this->onAfterReloadCallbacks[] = $callback;
    }

    /**
     * Register a callback for new TCP connections.
     *
     * Signature: function(Server $server, int $fd, int $reactorId): void
     */
    public function onConnect(Closure $callback): void
    {
        $this->onConnectCallbacks[] = $callback;
    }

    /**
     * Register a callback for TCP connection close.
     *
     * Signature: function(Server $server, int $fd, int $reactorId): void
     */
    public function onClose(Closure $callback): void
    {
        $this->onCloseCallbacks[] = $callback;
    }

    /**
     * Register a callback for task dispatch to a task worker.
     *
     * Signature: function(Server $server, Task $task): void
     */
    public function onTask(Closure $callback): void
    {
        $this->onTaskCallbacks[] = $callback;
    }

    /**
     * Register a callback for task worker completion.
     *
     * Signature: function(Server $server, int $taskId, mixed $data): void
     */
    public function onFinish(Closure $callback): void
    {
        $this->onFinishCallbacks[] = $callback;
    }

    /**
     * Register a callback for inter-worker pipe messages.
     *
     * Signature: function(Server $server, int $srcWorkerId, mixed $message): void
     */
    public function onPipeMessage(Closure $callback): void
    {
        $this->onPipeMessageCallbacks[] = $callback;
    }

    /**
     * Register a callback for WebSocket connection open.
     *
     * Signature: function(WebSocketServer $server, Request $request): void
     */
    public function onOpen(Closure $callback): void
    {
        $this->onOpenCallbacks[] = $callback;
    }

    /**
     * Register a callback for WebSocket message received.
     *
     * Signature: function(WebSocketServer $server, Frame $frame): void
     */
    public function onMessage(Closure $callback): void
    {
        $this->onMessageCallbacks[] = $callback;
    }

    /**
     * Register a callback for WebSocket handshake (overrides default).
     *
     * Only one handshake callback is supported — subsequent calls replace
     * the previous callback. The callback must return bool: true to accept
     * the connection, false to reject.
     *
     * Signature: function(Request $request, Response $response): bool
     */
    public function onHandshake(Closure $callback): void
    {
        $this->onHandshakeCallbacks = [$callback];
    }

    /**
     * Register a callback for WebSocket client disconnect.
     *
     * Signature: function(WebSocketServer $server, int $fd): void
     */
    public function onDisconnect(Closure $callback): void
    {
        $this->onDisconnectCallbacks[] = $callback;
    }

    /**
     * Push data to a WebSocket client.
     *
     * @param int $fd Connection file descriptor
     * @param string $data Data to send
     * @param int $opcode WebSocket opcode
     * @param int $flags WebSocket flags
     * @throws ServerException If the server has not been started
     */
    public function push(int $fd, string $data, int $opcode = 1, int $flags = 2): bool
    {
        $server = $this->getServer();

        if (!$server instanceof WebSocketServer) {
            throw new ServerException('Cannot push: server is not a WebSocket server.');
        }

        return $server->push($fd, $data, $opcode, $flags);
    }

    /**
     * Disconnect a WebSocket client.
     *
     * @param int $fd Connection file descriptor
     * @param int $code WebSocket close code
     * @param string $reason Close reason
     * @throws ServerException If the server has not been started
     */
    public function wsDisconnect(int $fd, int $code = 1000, string $reason = ''): bool
    {
        $server = $this->getServer();

        if (!$server instanceof WebSocketServer) {
            throw new ServerException('Cannot disconnect: server is not a WebSocket server.');
        }

        return $server->disconnect($fd, $code, $reason);
    }

    /**
     * Check if a WebSocket connection is established.
     *
     * @param int $fd Connection file descriptor
     * @throws ServerException If the server has not been started
     */
    public function isEstablished(int $fd): bool
    {
        $server = $this->getServer();

        if (!$server instanceof WebSocketServer) {
            throw new ServerException('Cannot check connection: server is not a WebSocket server.');
        }

        return $server->isEstablished($fd);
    }

    /**
     * Dispatch a task to a task worker.
     *
     * @param mixed $data Task data
     * @param int $dstWorkerId Target task worker (-1 for auto)
     * @param Closure|null $callback Optional finish callback
     * @throws ServerException If the server has not been started
     * @return int|false Task ID or false on failure
     */
    public function task(mixed $data, int $dstWorkerId = -1, Closure|null $callback = null): int|false
    {
        return $this->getServer()->task($data, $dstWorkerId, $callback);
    }

    /**
     * Dispatch multiple tasks and wait for results using coroutines.
     *
     * @param list<mixed> $tasks Array of task data
     * @param float $timeout Timeout in seconds
     * @throws ServerException If the server has not been started
     * @return array<mixed>|false Results array or false on failure
     */
    public function taskCo(array $tasks, float $timeout = 0.5): array|false
    {
        return $this->getServer()->taskCo($tasks, $timeout);
    }

    /**
     * Return task result from a task worker.
     *
     * @param mixed $data Result data
     * @throws ServerException If the server has not been started
     */
    public function finish(mixed $data): bool
    {
        return $this->getServer()->finish($data);
    }

    /**
     * Set a recurring timer.
     *
     * Timers are process-global (Swoole\Timer), not scoped to this server.
     * They persist until cleared or the process exits.
     *
     * @param int $ms Interval in milliseconds
     * @param Closure $callback Timer callback
     * @return int|false Timer ID or false on failure
     */
    public function tick(int $ms, Closure $callback): int|false
    {
        return Timer::tick($ms, $callback);
    }

    /**
     * Set a one-time timer.
     *
     * Timers are process-global (Swoole\Timer), not scoped to this server.
     *
     * @param int $ms Delay in milliseconds
     * @param Closure $callback Timer callback
     * @return int|false Timer ID or false on failure
     */
    public function after(int $ms, Closure $callback): int|false
    {
        return Timer::after($ms, $callback);
    }

    /**
     * Clear a timer.
     *
     * @param int $timerId Timer ID to clear
     */
    public function clearTimer(int $timerId): bool
    {
        return Timer::clear($timerId);
    }

    /**
     * Close a connection.
     *
     * @param int $fd Connection file descriptor
     * @param bool $reset Reset the connection instead of graceful close
     * @throws ServerException If the server has not been started
     */
    public function close(int $fd, bool $reset = false): bool
    {
        return $this->getServer()->close($fd, $reset);
    }

    /**
     * Check if a connection exists.
     *
     * @param int $fd Connection file descriptor
     * @throws ServerException If the server has not been started
     */
    public function exists(int $fd): bool
    {
        return $this->getServer()->exists($fd);
    }

    /**
     * Get connection information.
     *
     * @param int $fd Connection file descriptor
     * @throws ServerException If the server has not been started
     * @return array<mixed, mixed>|false Connection info or false
     */
    public function getClientInfo(int $fd): array|false
    {
        return $this->getServer()->getClientInfo($fd);
    }

    /**
     * Get a list of connected client file descriptors.
     *
     * @param int $startFd Start from this file descriptor
     * @param int $findCount Number of connections to return
     * @throws ServerException If the server has not been started
     * @return array<mixed, mixed>|false List of file descriptors or false
     */
    public function getClientList(int $startFd = 0, int $findCount = 10): array|false
    {
        return $this->getServer()->getClientList($startFd, $findCount);
    }

    /**
     * Send a message to another worker process.
     *
     * @param mixed $message Message data
     * @param int $dstWorkerId Target worker ID
     * @throws ServerException If the server has not been started
     */
    public function sendMessage(mixed $message, int $dstWorkerId): bool
    {
        return $this->getServer()->sendMessage($message, $dstWorkerId);
    }

    /**
     * Get server statistics.
     *
     * @throws ServerException If the server has not been started
     * @return array<mixed, mixed>
     */
    public function stats(): array
    {
        return $this->getServer()->stats();
    }

    /**
     * Get the current worker ID.
     *
     * @throws ServerException If the server has not been started
     */
    public function getWorkerId(): int|false
    {
        return $this->getServer()->getWorkerId();
    }

    /**
     * Get the PID of a worker process.
     *
     * @param int $workerId Worker ID (-1 for current)
     * @throws ServerException If the server has not been started
     */
    public function getWorkerPid(int $workerId = -1): int|false
    {
        return $this->getServer()->getWorkerPid($workerId);
    }

    /**
     * Get the status of a worker process.
     *
     * @param int $workerId Worker ID (-1 for current)
     * @throws ServerException If the server has not been started
     */
    public function getWorkerStatus(int $workerId = -1): int|false
    {
        return $this->getServer()->getWorkerStatus($workerId);
    }

    /**
     * Get the master process PID.
     *
     * @throws ServerException If the server has not been started
     */
    public function getMasterPid(): int
    {
        return $this->getServer()->getMasterPid();
    }

    /**
     * Get the manager process PID.
     *
     * @throws ServerException If the server has not been started
     */
    public function getManagerPid(): int
    {
        return $this->getServer()->getManagerPid();
    }

    /**
     * Get the underlying Swoole server instance.
     *
     * Use this for advanced Swoole features not directly exposed
     * by this class (addProcess, addListener, bind, protect, etc.).
     *
     * @throws ServerException If the server has not been started
     */
    public function getServer(): Server
    {
        if ($this->server === null) {
            throw new ServerException('Server has not been started. Call serve() first.');
        }

        return $this->server;
    }

    /**
     * Check whether any WebSocket callbacks are registered.
     */
    private function hasWebSocket(): bool
    {
        return $this->onOpenCallbacks !== []
            || $this->onMessageCallbacks !== []
            || $this->onHandshakeCallbacks !== []
            || $this->onDisconnectCallbacks !== [];
    }

    /**
     * Convert a Swoole request to a PSR-7 server request.
     *
     * @param \Swoole\Http\Request $swooleRequest The Swoole request to convert
     */
    public function convertRequest(\Swoole\Http\Request $swooleRequest): \Psr\Http\Message\ServerRequestInterface
    {
        return $this->requestConverter->toServerRequest($swooleRequest);
    }

    /**
     * Create the appropriate Swoole server instance.
     */
    private function createServer(string $host, int $port): Server
    {
        if ($this->hasWebSocket()) {
            return new WebSocketServer($host, $port, $this->config->mode, $this->config->sockType);
        }

        return new Server($host, $port, $this->config->mode, $this->config->sockType);
    }

    /**
     * Register all event callbacks on the server.
     */
    private function registerCallbacks(Server $server): void
    {
        $callbackMap = [
            'start' => $this->onStartCallbacks,
            'managerStart' => $this->onManagerStartCallbacks,
            'managerStop' => $this->onManagerStopCallbacks,
            'workerStart' => $this->onWorkerStartCallbacks,
            'workerStop' => $this->onWorkerStopCallbacks,
            'workerExit' => $this->onWorkerExitCallbacks,
            'workerError' => $this->onWorkerErrorCallbacks,
            'beforeShutdown' => $this->onBeforeShutdownCallbacks,
            'shutdown' => $this->onShutdownCallbacks,
            'beforeReload' => $this->onBeforeReloadCallbacks,
            'afterReload' => $this->onAfterReloadCallbacks,
            'connect' => $this->onConnectCallbacks,
            'close' => $this->onCloseCallbacks,
            'task' => $this->onTaskCallbacks,
            'finish' => $this->onFinishCallbacks,
            'pipeMessage' => $this->onPipeMessageCallbacks,
            'open' => $this->onOpenCallbacks,
            'message' => $this->onMessageCallbacks,
            'handshake' => $this->onHandshakeCallbacks,
            'disconnect' => $this->onDisconnectCallbacks,
        ];

        foreach ($callbackMap as $event => $callbacks) {
            if ($callbacks === []) {
                continue;
            }

            if ($event === 'handshake') {
                $server->on('handshake', $callbacks[array_key_last($callbacks)]);
                continue;
            }

            $server->on($event, static function () use ($callbacks): void {
                /** @var list<mixed> $args */
                $args = func_get_args();
                foreach ($callbacks as $callback) {
                    $callback(...$args);
                }
            });
        }
    }
}
