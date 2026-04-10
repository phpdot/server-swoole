<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Config;

use Closure;

/**
 * ServerConfig.
 *
 * Immutable configuration object for the Swoole HTTP server.
 * All with*() mutators return a cloned instance. Lifecycle callbacks
 * are stored as arrays of Closures and appended via on*() methods.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ServerConfig
{
    /** @var int|null Worker process count (null = swoole_cpu_num()) */
    private int|null $workerNum = null;

    /** @var int Task worker process count */
    private int $taskWorkerNum = 0;

    /** @var int Maximum requests per worker before restart */
    private int $maxRequest = 100000;

    /** @var int Maximum coroutines per worker */
    private int $maxCoroutine = 100000;

    /** @var int Server mode (SWOOLE_PROCESS = 2, SWOOLE_BASE = 1) */
    private int $mode = SWOOLE_BASE;

    /** @var bool Run as daemon */
    private bool $daemonize = false;

    /** @var string PID file path */
    private string $pidFile = '';

    /** @var string Log file path */
    private string $logFile = '';

    /** @var int Log level (SWOOLE_LOG_INFO = 2) */
    private int $logLevel = 2;

    /** @var int TCP backlog queue size */
    private int $backlog = 128;

    /** @var bool Enable TCP nodelay */
    private bool $tcpNodelay = true;

    /** @var bool Enable TCP keepalive */
    private bool $tcpKeepalive = false;

    /** @var int Output buffer size in bytes */
    private int $bufferOutputSize = 2097152;

    /** @var int Socket buffer size in bytes */
    private int $socketBufferSize = 8388608;

    /** @var int Maximum package length in bytes */
    private int $packageMaxLength = 2097152;

    /** @var bool Parse POST data automatically */
    private bool $httpParsePost = true;

    /** @var bool Parse cookies automatically */
    private bool $httpParseCookie = true;

    /** @var bool Parse uploaded files automatically */
    private bool $httpParseFiles = true;

    /** @var bool Enable HTTP compression */
    private bool $httpCompression = true;

    /** @var int Minimum response size for compression */
    private int $httpCompressionMinLength = 20;

    /** @var int Compression level */
    private int $httpCompressionLevel = 1;

    /** @var string Temporary directory for uploads */
    private string $uploadTmpDir = '/tmp';

    /** @var bool Enable static file handler */
    private bool $staticHandler = false;

    /** @var string Document root for static files */
    private string $documentRoot = '';

    /** @var list<string> Static handler location paths */
    private array $staticHandlerLocations = [];

    /** @var int Socket type (SWOOLE_SOCK_TCP = 1) */
    private int $sockType = 1;

    /** @var string SSL certificate file path */
    private string $sslCertFile = '';

    /** @var string SSL private key file path */
    private string $sslKeyFile = '';

    /** @var string SSL CA file path */
    private string $sslCaFile = '';

    /** @var bool Verify SSL peer */
    private bool $sslVerifyPeer = false;

    /** @var int SSL protocols bitmask (0 = Swoole default TLSv1.2+1.3) */
    private int $sslProtocols = 0;

    /** @var string SSL cipher list */
    private string $sslCiphers = '';

    /** @var bool Enable HTTP/2 protocol */
    private bool $http2 = false;

    /** @var array<string, mixed> Raw Swoole settings to merge underneath typed settings */
    private array $rawSettings = [];

    /** @var list<Closure> Callbacks invoked on server start */
    private array $onStartCallbacks = [];

    /** @var list<Closure> Callbacks invoked on manager process start */
    private array $onManagerStartCallbacks = [];

    /** @var list<Closure> Callbacks invoked on worker process start */
    private array $onWorkerStartCallbacks = [];

    /** @var list<Closure> Callbacks invoked on worker process stop */
    private array $onWorkerStopCallbacks = [];

    /** @var list<Closure> Callbacks invoked on worker process exit */
    private array $onWorkerExitCallbacks = [];

    /** @var list<Closure> Callbacks invoked on worker process error */
    private array $onWorkerErrorCallbacks = [];

    /** @var list<Closure> Callbacks invoked before server shutdown */
    private array $onBeforeShutdownCallbacks = [];

    /** @var list<Closure> Callbacks invoked on server shutdown */
    private array $onShutdownCallbacks = [];

    /**
     * Create a new ServerConfig with production-ready defaults.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Set the number of worker processes.
     *
     * @param int|null $workerNum Number of workers, or null for swoole_cpu_num()
     */
    public function withWorkerNum(int|null $workerNum): self
    {
        $clone = clone $this;
        $clone->workerNum = $workerNum;
        return $clone;
    }

    /**
     * Set the number of task worker processes.
     *
     * @param int $taskWorkerNum Number of task workers
     */
    public function withTaskWorkerNum(int $taskWorkerNum): self
    {
        $clone = clone $this;
        $clone->taskWorkerNum = $taskWorkerNum;
        return $clone;
    }

    /**
     * Set the maximum number of requests per worker before restart.
     *
     * @param int $maxRequest Maximum request count
     */
    public function withMaxRequest(int $maxRequest): self
    {
        $clone = clone $this;
        $clone->maxRequest = $maxRequest;
        return $clone;
    }

    /**
     * Set the maximum number of coroutines per worker.
     *
     * @param int $maxCoroutine Maximum coroutine count
     */
    public function withMaxCoroutine(int $maxCoroutine): self
    {
        $clone = clone $this;
        $clone->maxCoroutine = $maxCoroutine;
        return $clone;
    }

    /**
     * Set the server mode.
     *
     * @param int $mode SWOOLE_PROCESS (3) or SWOOLE_BASE (1)
     */
    public function withMode(int $mode): self
    {
        $clone = clone $this;
        $clone->mode = $mode;
        return $clone;
    }

    /**
     * Set whether the server runs as a daemon.
     *
     * @param bool $daemonize Enable or disable daemonization
     */
    public function withDaemonize(bool $daemonize): self
    {
        $clone = clone $this;
        $clone->daemonize = $daemonize;
        return $clone;
    }

    /**
     * Set the PID file path.
     *
     * @param string $pidFile Path to PID file
     */
    public function withPidFile(string $pidFile): self
    {
        $clone = clone $this;
        $clone->pidFile = $pidFile;
        return $clone;
    }

    /**
     * Set the log file path.
     *
     * @param string $logFile Path to log file
     */
    public function withLogFile(string $logFile): self
    {
        $clone = clone $this;
        $clone->logFile = $logFile;
        return $clone;
    }

    /**
     * Set the log level.
     *
     * @param int $logLevel Swoole log level constant value
     */
    public function withLogLevel(int $logLevel): self
    {
        $clone = clone $this;
        $clone->logLevel = $logLevel;
        return $clone;
    }

    /**
     * Set the TCP backlog queue size.
     *
     * @param int $backlog Backlog size
     */
    public function withBacklog(int $backlog): self
    {
        $clone = clone $this;
        $clone->backlog = $backlog;
        return $clone;
    }

    /**
     * Set whether TCP nodelay is enabled.
     *
     * @param bool $tcpNodelay Enable or disable TCP nodelay
     */
    public function withTcpNodelay(bool $tcpNodelay): self
    {
        $clone = clone $this;
        $clone->tcpNodelay = $tcpNodelay;
        return $clone;
    }

    /**
     * Set whether TCP keepalive is enabled.
     *
     * @param bool $tcpKeepalive Enable or disable TCP keepalive
     */
    public function withTcpKeepalive(bool $tcpKeepalive): self
    {
        $clone = clone $this;
        $clone->tcpKeepalive = $tcpKeepalive;
        return $clone;
    }

    /**
     * Set the output buffer size.
     *
     * @param int $bufferOutputSize Buffer size in bytes
     */
    public function withBufferOutputSize(int $bufferOutputSize): self
    {
        $clone = clone $this;
        $clone->bufferOutputSize = $bufferOutputSize;
        return $clone;
    }

    /**
     * Set the socket buffer size.
     *
     * @param int $socketBufferSize Buffer size in bytes
     */
    public function withSocketBufferSize(int $socketBufferSize): self
    {
        $clone = clone $this;
        $clone->socketBufferSize = $socketBufferSize;
        return $clone;
    }

    /**
     * Set the maximum package length.
     *
     * @param int $packageMaxLength Maximum length in bytes
     */
    public function withPackageMaxLength(int $packageMaxLength): self
    {
        $clone = clone $this;
        $clone->packageMaxLength = $packageMaxLength;
        return $clone;
    }

    /**
     * Set whether to parse POST data automatically.
     *
     * @param bool $httpParsePost Enable or disable POST parsing
     */
    public function withHttpParsePost(bool $httpParsePost): self
    {
        $clone = clone $this;
        $clone->httpParsePost = $httpParsePost;
        return $clone;
    }

    /**
     * Set whether to parse cookies automatically.
     *
     * @param bool $httpParseCookie Enable or disable cookie parsing
     */
    public function withHttpParseCookie(bool $httpParseCookie): self
    {
        $clone = clone $this;
        $clone->httpParseCookie = $httpParseCookie;
        return $clone;
    }

    /**
     * Set whether to parse uploaded files automatically.
     *
     * @param bool $httpParseFiles Enable or disable file parsing
     */
    public function withHttpParseFiles(bool $httpParseFiles): self
    {
        $clone = clone $this;
        $clone->httpParseFiles = $httpParseFiles;
        return $clone;
    }

    /**
     * Set whether HTTP compression is enabled.
     *
     * @param bool $httpCompression Enable or disable compression
     */
    public function withHttpCompression(bool $httpCompression): self
    {
        $clone = clone $this;
        $clone->httpCompression = $httpCompression;
        return $clone;
    }

    /**
     * Set the minimum response size for compression.
     *
     * @param int $httpCompressionMinLength Minimum length in bytes
     */
    public function withHttpCompressionMinLength(int $httpCompressionMinLength): self
    {
        $clone = clone $this;
        $clone->httpCompressionMinLength = $httpCompressionMinLength;
        return $clone;
    }

    /**
     * Set the compression level.
     *
     * @param int $httpCompressionLevel Compression level
     */
    public function withHttpCompressionLevel(int $httpCompressionLevel): self
    {
        $clone = clone $this;
        $clone->httpCompressionLevel = $httpCompressionLevel;
        return $clone;
    }

    /**
     * Set the temporary directory for file uploads.
     *
     * @param string $uploadTmpDir Directory path
     */
    public function withUploadTmpDir(string $uploadTmpDir): self
    {
        $clone = clone $this;
        $clone->uploadTmpDir = $uploadTmpDir;
        return $clone;
    }

    /**
     * Set whether the static file handler is enabled.
     *
     * @param bool $staticHandler Enable or disable static file serving
     */
    public function withStaticHandler(bool $staticHandler): self
    {
        $clone = clone $this;
        $clone->staticHandler = $staticHandler;
        return $clone;
    }

    /**
     * Set the document root for static files.
     *
     * @param string $documentRoot Document root path
     */
    public function withDocumentRoot(string $documentRoot): self
    {
        $clone = clone $this;
        $clone->documentRoot = $documentRoot;
        return $clone;
    }

    /**
     * Set the static handler location paths.
     *
     * @param list<string> $staticHandlerLocations Location paths
     */
    public function withStaticHandlerLocations(array $staticHandlerLocations): self
    {
        $clone = clone $this;
        $clone->staticHandlerLocations = $staticHandlerLocations;
        return $clone;
    }

    /**
     * Set the socket type.
     *
     * @param int $sockType Socket type constant value
     */
    public function withSockType(int $sockType): self
    {
        $clone = clone $this;
        $clone->sockType = $sockType;
        return $clone;
    }

    /**
     * Set the SSL certificate file path.
     *
     * @param string $sslCertFile Path to certificate file
     */
    public function withSslCertFile(string $sslCertFile): self
    {
        $clone = clone $this;
        $clone->sslCertFile = $sslCertFile;
        return $clone;
    }

    /**
     * Set the SSL private key file path.
     *
     * @param string $sslKeyFile Path to key file
     */
    public function withSslKeyFile(string $sslKeyFile): self
    {
        $clone = clone $this;
        $clone->sslKeyFile = $sslKeyFile;
        return $clone;
    }

    /**
     * Set the SSL CA file path.
     *
     * @param string $sslCaFile Path to CA file
     */
    public function withSslCaFile(string $sslCaFile): self
    {
        $clone = clone $this;
        $clone->sslCaFile = $sslCaFile;
        return $clone;
    }

    /**
     * Set whether to verify the SSL peer.
     *
     * @param bool $sslVerifyPeer Enable or disable peer verification
     */
    public function withSslVerifyPeer(bool $sslVerifyPeer): self
    {
        $clone = clone $this;
        $clone->sslVerifyPeer = $sslVerifyPeer;
        return $clone;
    }

    /**
     * Set the SSL protocols bitmask.
     *
     * @param int $sslProtocols Protocols bitmask
     */
    public function withSslProtocols(int $sslProtocols): self
    {
        $clone = clone $this;
        $clone->sslProtocols = $sslProtocols;
        return $clone;
    }

    /**
     * Set the SSL cipher list.
     *
     * @param string $sslCiphers Cipher list string
     */
    public function withSslCiphers(string $sslCiphers): self
    {
        $clone = clone $this;
        $clone->sslCiphers = $sslCiphers;
        return $clone;
    }

    /**
     * Set whether HTTP/2 protocol is enabled.
     *
     * @param bool $http2 Enable or disable HTTP/2
     */
    public function withHttp2(bool $http2): self
    {
        $clone = clone $this;
        $clone->http2 = $http2;
        return $clone;
    }

    /**
     * Set raw Swoole settings to merge underneath typed settings.
     *
     * @param array<string, mixed> $rawSettings Raw settings array
     */
    public function withRawSettings(array $rawSettings): self
    {
        $clone = clone $this;
        $clone->rawSettings = $rawSettings;
        return $clone;
    }

    /**
     * Append a callback for the server start event.
     *
     * @param Closure $callback Callback to invoke on start
     */
    public function onStart(Closure $callback): self
    {
        $clone = clone $this;
        $clone->onStartCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Append a callback for the manager process start event.
     *
     * @param Closure $callback Callback to invoke on manager start
     */
    public function onManagerStart(Closure $callback): self
    {
        $clone = clone $this;
        $clone->onManagerStartCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Append a callback for the worker process start event.
     *
     * @param Closure $callback Callback to invoke on worker start
     */
    public function onWorkerStart(Closure $callback): self
    {
        $clone = clone $this;
        $clone->onWorkerStartCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Append a callback for the worker process stop event.
     *
     * @param Closure $callback Callback to invoke on worker stop
     */
    public function onWorkerStop(Closure $callback): self
    {
        $clone = clone $this;
        $clone->onWorkerStopCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Append a callback for the worker process exit event.
     *
     * @param Closure $callback Callback to invoke on worker exit
     */
    public function onWorkerExit(Closure $callback): self
    {
        $clone = clone $this;
        $clone->onWorkerExitCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Append a callback for the worker process error event.
     *
     * @param Closure $callback Callback to invoke on worker error
     */
    public function onWorkerError(Closure $callback): self
    {
        $clone = clone $this;
        $clone->onWorkerErrorCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Append a callback for the before-shutdown event.
     *
     * @param Closure $callback Callback to invoke before shutdown
     */
    public function onBeforeShutdown(Closure $callback): self
    {
        $clone = clone $this;
        $clone->onBeforeShutdownCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Append a callback for the server shutdown event.
     *
     * @param Closure $callback Callback to invoke on shutdown
     */
    public function onShutdown(Closure $callback): self
    {
        $clone = clone $this;
        $clone->onShutdownCallbacks[] = $callback;
        return $clone;
    }

    /**
     * Get the server mode.
     */
    public function getMode(): int
    {
        return $this->mode;
    }

    /**
     * Get the socket type.
     */
    public function getSockType(): int
    {
        return $this->sockType;
    }

    /**
     * Get all lifecycle callbacks keyed by Swoole event name.
     *
     * @return array<string, list<Closure>>
     */
    public function getCallbacks(): array
    {
        return [
            'start' => $this->onStartCallbacks,
            'managerStart' => $this->onManagerStartCallbacks,
            'workerStart' => $this->onWorkerStartCallbacks,
            'workerStop' => $this->onWorkerStopCallbacks,
            'workerExit' => $this->onWorkerExitCallbacks,
            'workerError' => $this->onWorkerErrorCallbacks,
            'beforeShutdown' => $this->onBeforeShutdownCallbacks,
            'shutdown' => $this->onShutdownCallbacks,
        ];
    }

    /**
     * Build the settings array for Swoole's Server::set() method.
     *
     * Uses swoole_cpu_num() for worker_num when null. Only includes
     * non-default or non-empty values for optional string settings.
     * Typed settings override raw settings.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $settings = [
            'worker_num' => $this->workerNum ?? swoole_cpu_num(),
            'task_worker_num' => $this->taskWorkerNum,
            'max_request' => $this->maxRequest,
            'max_coroutine' => $this->maxCoroutine,
            'daemonize' => $this->daemonize,
            'log_level' => $this->logLevel,
            'backlog' => $this->backlog,
            'open_tcp_nodelay' => $this->tcpNodelay,
            'open_tcp_keepalive' => $this->tcpKeepalive,
            'buffer_output_size' => $this->bufferOutputSize,
            'socket_buffer_size' => $this->socketBufferSize,
            'package_max_length' => $this->packageMaxLength,
            'http_parse_post' => $this->httpParsePost,
            'http_parse_cookie' => $this->httpParseCookie,
            'http_parse_files' => $this->httpParseFiles,
            'http_compression' => $this->httpCompression,
            'http_compression_min_length' => $this->httpCompressionMinLength,
            'http_compression_level' => $this->httpCompressionLevel,
            'upload_tmp_dir' => $this->uploadTmpDir,
            'enable_static_handler' => $this->staticHandler,
            'ssl_verify_peer' => $this->sslVerifyPeer,
            'open_http2_protocol' => $this->http2,
            'enable_coroutine' => true,
        ];

        if ($this->pidFile !== '') {
            $settings['pid_file'] = $this->pidFile;
        }

        if ($this->logFile !== '') {
            $settings['log_file'] = $this->logFile;
        }

        if ($this->documentRoot !== '') {
            $settings['document_root'] = $this->documentRoot;
        }

        if ($this->staticHandlerLocations !== []) {
            $settings['static_handler_locations'] = $this->staticHandlerLocations;
        }

        if ($this->sslCertFile !== '') {
            $settings['ssl_cert_file'] = $this->sslCertFile;
        }

        if ($this->sslKeyFile !== '') {
            $settings['ssl_key_file'] = $this->sslKeyFile;
        }

        if ($this->sslCaFile !== '') {
            $settings['ssl_ca_file'] = $this->sslCaFile;
        }

        if ($this->sslProtocols > 0) {
            $settings['ssl_protocols'] = $this->sslProtocols;
        }

        if ($this->sslCiphers !== '') {
            $settings['ssl_ciphers'] = $this->sslCiphers;
        }

        return array_merge($this->rawSettings, $settings);
    }
}
