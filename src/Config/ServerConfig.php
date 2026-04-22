<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Config;

/**
 * ServerConfig.
 *
 * Immutable configuration for the Swoole HTTP server.
 * All properties are readonly and set via the constructor.
 * Use toArray() to build the settings array for Swoole's Server::set().
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class ServerConfig
{
    /**
     * @param int|null $workerNum Worker count (null = swoole_cpu_num())
     * @param int $taskWorkerNum Task worker count
     * @param int $maxRequest Max requests per worker before restart
     * @param int $maxCoroutine Max coroutines per worker
     * @param int $mode Swoole server mode. Use SWOOLE_PROCESS or SWOOLE_BASE — the numeric values differ across Swoole versions.
     * @param int $sockType Swoole socket type. Use SWOOLE_SOCK_TCP (add SWOOLE_SSL for TLS).
     * @param bool $daemonize Run as daemon
     * @param string $pidFile PID file path
     * @param string $logFile Log file path
     * @param int $logLevel SWOOLE_LOG_* constant
     * @param int $backlog TCP backlog queue size
     * @param bool $tcpNodelay Enable TCP nodelay
     * @param bool $tcpKeepalive Enable TCP keepalive
     * @param int $bufferOutputSize Output buffer size in bytes
     * @param int $socketBufferSize Socket buffer size in bytes
     * @param int $packageMaxLength Max package length in bytes
     * @param bool $httpParsePost Parse POST data automatically
     * @param bool $httpParseCookie Parse cookies automatically
     * @param bool $httpParseFiles Parse uploaded files automatically
     * @param bool $httpCompression Enable HTTP compression
     * @param int $httpCompressionMinLength Min response size for compression
     * @param int $httpCompressionLevel Compression level
     * @param string $uploadTmpDir Temp directory for uploads
     * @param bool $staticHandler Enable static file serving
     * @param string $documentRoot Document root for static files
     * @param list<string> $staticHandlerLocations Static handler paths
     * @param string $sslCertFile SSL certificate file path
     * @param string $sslKeyFile SSL private key file path
     * @param string $sslCaFile SSL CA file path
     * @param bool $sslVerifyPeer Verify SSL peer
     * @param int $sslProtocols SSL protocols bitmask (0 = Swoole default)
     * @param string $sslCiphers SSL cipher list
     * @param bool $http2 Enable HTTP/2 protocol
     * @param int $hookFlags `Swoole\Runtime::enableCoroutine()` flags — applied
     *                       in the master before worker fork, so every worker and user
     *                       process inherits. Defaults to `SWOOLE_HOOK_ALL` so blocking PHP
     *                       I/O (phpredis, PDO, cURL, file, mysqli) yields the coroutine
     *                       scheduler instead of blocking the whole worker. Set to 0 to
     *                       opt out; use a narrower mask to hook selectively.
     * @param array<string, mixed> $rawSettings Extra Swoole settings merged underneath typed settings
     */
    public function __construct(
        public readonly int|null $workerNum = null,
        public readonly int $taskWorkerNum = 0,
        public readonly int $maxRequest = 100000,
        public readonly int $maxCoroutine = 100000,
        public readonly int $mode = SWOOLE_PROCESS,
        public readonly int $sockType = SWOOLE_SOCK_TCP,
        public readonly bool $daemonize = false,
        public readonly string $pidFile = '',
        public readonly string $logFile = '',
        public readonly int $logLevel = 2,
        public readonly int $backlog = 128,
        public readonly bool $tcpNodelay = true,
        public readonly bool $tcpKeepalive = false,
        public readonly int $bufferOutputSize = 2097152,
        public readonly int $socketBufferSize = 8388608,
        public readonly int $packageMaxLength = 2097152,
        public readonly bool $httpParsePost = true,
        public readonly bool $httpParseCookie = true,
        public readonly bool $httpParseFiles = true,
        public readonly bool $httpCompression = true,
        public readonly int $httpCompressionMinLength = 20,
        public readonly int $httpCompressionLevel = 1,
        public readonly string $uploadTmpDir = '/tmp',
        public readonly bool $staticHandler = false,
        public readonly string $documentRoot = '',
        public readonly array $staticHandlerLocations = [],
        public readonly string $sslCertFile = '',
        public readonly string $sslKeyFile = '',
        public readonly string $sslCaFile = '',
        public readonly bool $sslVerifyPeer = false,
        public readonly int $sslProtocols = 0,
        public readonly string $sslCiphers = '',
        public readonly bool $http2 = false,
        public readonly int $hookFlags = SWOOLE_HOOK_ALL,
        public readonly array $rawSettings = [],
    ) {}

    /**
     * Build the settings array for Swoole's Server::set().
     *
     * Typed settings override raw settings. Only includes non-empty
     * optional string values. Uses swoole_cpu_num() when workerNum is null.
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
