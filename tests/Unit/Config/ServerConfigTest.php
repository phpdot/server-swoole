<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Unit\Config;

use PHPdot\Server\Swoole\Config\ServerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerConfigTest extends TestCase
{
    #[Test]
    public function defaultValuesAreCorrect(): void
    {
        $config = new ServerConfig();

        self::assertNull($config->workerNum);
        self::assertSame(0, $config->taskWorkerNum);
        self::assertSame(100000, $config->maxRequest);
        self::assertSame(100000, $config->maxCoroutine);
        self::assertSame(3, $config->mode);
        self::assertSame(1, $config->sockType);
        self::assertFalse($config->daemonize);
        self::assertSame('', $config->pidFile);
        self::assertSame('', $config->logFile);
        self::assertSame(2, $config->logLevel);
        self::assertSame(128, $config->backlog);
        self::assertTrue($config->tcpNodelay);
        self::assertFalse($config->tcpKeepalive);
        self::assertSame(2097152, $config->bufferOutputSize);
        self::assertSame(8388608, $config->socketBufferSize);
        self::assertSame(2097152, $config->packageMaxLength);
        self::assertTrue($config->httpParsePost);
        self::assertTrue($config->httpParseCookie);
        self::assertTrue($config->httpParseFiles);
        self::assertTrue($config->httpCompression);
        self::assertSame(20, $config->httpCompressionMinLength);
        self::assertSame(1, $config->httpCompressionLevel);
        self::assertSame('/tmp', $config->uploadTmpDir);
        self::assertFalse($config->staticHandler);
        self::assertSame('', $config->documentRoot);
        self::assertSame([], $config->staticHandlerLocations);
        self::assertSame('', $config->sslCertFile);
        self::assertSame('', $config->sslKeyFile);
        self::assertSame('', $config->sslCaFile);
        self::assertFalse($config->sslVerifyPeer);
        self::assertSame(0, $config->sslProtocols);
        self::assertSame('', $config->sslCiphers);
        self::assertFalse($config->http2);
        self::assertSame([], $config->rawSettings);
    }

    #[Test]
    public function customValuesViaNamedParams(): void
    {
        $config = new ServerConfig(
            workerNum: 8,
            taskWorkerNum: 4,
            maxRequest: 5000,
            mode: 1,
            daemonize: true,
            http2: true,
        );

        self::assertSame(8, $config->workerNum);
        self::assertSame(4, $config->taskWorkerNum);
        self::assertSame(5000, $config->maxRequest);
        self::assertSame(1, $config->mode);
        self::assertTrue($config->daemonize);
        self::assertTrue($config->http2);
    }

    #[Test]
    public function toArrayIncludesWorkerNumFromSwooleCpuNum(): void
    {
        $config = new ServerConfig();

        self::assertSame(swoole_cpu_num(), $config->toArray()['worker_num']);
    }

    #[Test]
    public function toArrayUsesExplicitWorkerNum(): void
    {
        $config = new ServerConfig(workerNum: 4);

        self::assertSame(4, $config->toArray()['worker_num']);
    }

    #[Test]
    public function toArrayIncludesAllExpectedDefaults(): void
    {
        $config = new ServerConfig();
        $array = $config->toArray();

        self::assertTrue($array['enable_coroutine']);
        self::assertTrue($array['open_tcp_nodelay']);
        self::assertTrue($array['http_compression']);
        self::assertTrue($array['http_parse_post']);
        self::assertTrue($array['http_parse_cookie']);
        self::assertTrue($array['http_parse_files']);
        self::assertFalse($array['daemonize']);
        self::assertFalse($array['open_tcp_keepalive']);
        self::assertFalse($array['enable_static_handler']);
        self::assertFalse($array['ssl_verify_peer']);
        self::assertFalse($array['open_http2_protocol']);
        self::assertSame(100000, $array['max_request']);
        self::assertSame(100000, $array['max_coroutine']);
        self::assertSame(0, $array['task_worker_num']);
        self::assertSame(2, $array['log_level']);
        self::assertSame(128, $array['backlog']);
        self::assertSame(2097152, $array['buffer_output_size']);
        self::assertSame(8388608, $array['socket_buffer_size']);
        self::assertSame(2097152, $array['package_max_length']);
        self::assertSame(20, $array['http_compression_min_length']);
        self::assertSame(1, $array['http_compression_level']);
        self::assertSame('/tmp', $array['upload_tmp_dir']);
    }

    #[Test]
    public function toArrayExcludesPidFileWhenEmpty(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('pid_file', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesPidFileWhenSet(): void
    {
        $config = new ServerConfig(pidFile: '/var/run/swoole.pid');

        self::assertSame('/var/run/swoole.pid', $config->toArray()['pid_file']);
    }

    #[Test]
    public function toArrayExcludesLogFileWhenEmpty(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('log_file', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesLogFileWhenSet(): void
    {
        $config = new ServerConfig(logFile: '/var/log/swoole.log');

        self::assertSame('/var/log/swoole.log', $config->toArray()['log_file']);
    }

    #[Test]
    public function toArrayExcludesDocumentRootWhenEmpty(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('document_root', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesDocumentRootWhenSet(): void
    {
        $config = new ServerConfig(documentRoot: '/var/www/public');

        self::assertSame('/var/www/public', $config->toArray()['document_root']);
    }

    #[Test]
    public function toArrayExcludesStaticHandlerLocationsWhenEmpty(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('static_handler_locations', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesStaticHandlerLocationsWhenSet(): void
    {
        $locations = ['/assets', '/images'];
        $config = new ServerConfig(staticHandlerLocations: $locations);

        self::assertSame($locations, $config->toArray()['static_handler_locations']);
    }

    #[Test]
    public function toArrayExcludesSslCertFileWhenEmpty(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('ssl_cert_file', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesSslCertFileWhenSet(): void
    {
        $config = new ServerConfig(sslCertFile: '/etc/ssl/cert.pem');

        self::assertSame('/etc/ssl/cert.pem', $config->toArray()['ssl_cert_file']);
    }

    #[Test]
    public function toArrayExcludesSslKeyFileWhenEmpty(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('ssl_key_file', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesSslKeyFileWhenSet(): void
    {
        $config = new ServerConfig(sslKeyFile: '/etc/ssl/key.pem');

        self::assertSame('/etc/ssl/key.pem', $config->toArray()['ssl_key_file']);
    }

    #[Test]
    public function toArrayExcludesSslCaFileWhenEmpty(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('ssl_ca_file', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesSslCaFileWhenSet(): void
    {
        $config = new ServerConfig(sslCaFile: '/etc/ssl/ca.pem');

        self::assertSame('/etc/ssl/ca.pem', $config->toArray()['ssl_ca_file']);
    }

    #[Test]
    public function toArrayExcludesSslProtocolsWhenZero(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('ssl_protocols', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesSslProtocolsWhenSet(): void
    {
        $config = new ServerConfig(sslProtocols: 6);

        self::assertSame(6, $config->toArray()['ssl_protocols']);
    }

    #[Test]
    public function toArrayExcludesSslCiphersWhenEmpty(): void
    {
        $config = new ServerConfig();

        self::assertArrayNotHasKey('ssl_ciphers', $config->toArray());
    }

    #[Test]
    public function toArrayIncludesSslCiphersWhenSet(): void
    {
        $config = new ServerConfig(sslCiphers: 'ECDHE-RSA-AES128-GCM-SHA256');

        self::assertSame('ECDHE-RSA-AES128-GCM-SHA256', $config->toArray()['ssl_ciphers']);
    }

    #[Test]
    public function toArrayRawSettingsMergedTypedTakesPrecedence(): void
    {
        $config = new ServerConfig(
            workerNum: 8,
            rawSettings: [
                'worker_num' => 2,
                'custom_setting' => 'value',
            ],
        );

        $array = $config->toArray();

        self::assertSame(8, $array['worker_num']);
        self::assertSame('value', $array['custom_setting']);
    }

    #[Test]
    public function toArrayEnablesHttp2(): void
    {
        $config = new ServerConfig(http2: true);

        self::assertTrue($config->toArray()['open_http2_protocol']);
    }

    #[Test]
    public function toArrayDisablesHttpCompression(): void
    {
        $config = new ServerConfig(httpCompression: false);

        self::assertFalse($config->toArray()['http_compression']);
    }

    #[Test]
    public function toArraySetsCompressionLevel(): void
    {
        $config = new ServerConfig(httpCompressionLevel: 6);

        self::assertSame(6, $config->toArray()['http_compression_level']);
    }

    #[Test]
    public function toArrayEnablesStaticHandler(): void
    {
        $config = new ServerConfig(staticHandler: true);

        self::assertTrue($config->toArray()['enable_static_handler']);
    }
}
