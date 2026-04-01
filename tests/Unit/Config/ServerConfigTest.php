<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Unit\Config;

use PHPdot\Server\Swoole\Config\ServerConfig;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ServerConfigTest extends TestCase
{
    #[Test]
    public function defaultReturnsInstance(): void
    {
        $config = ServerConfig::default();

        self::assertInstanceOf(ServerConfig::class, $config);
    }

    #[Test]
    public function toArrayIncludesWorkerNumFromSwooleCpuNum(): void
    {
        $config = ServerConfig::default();
        $array = $config->toArray();

        self::assertSame(swoole_cpu_num(), $array['worker_num']);
    }

    #[Test]
    public function toArrayIncludesAllExpectedDefaults(): void
    {
        $config = ServerConfig::default();
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
    public function withWorkerNumChangesWorkerNumInToArray(): void
    {
        $config = ServerConfig::default()->withWorkerNum(4);

        self::assertSame(4, $config->toArray()['worker_num']);
    }

    #[Test]
    public function withMaxRequestChangesMaxRequest(): void
    {
        $config = ServerConfig::default()->withMaxRequest(5000);

        self::assertSame(5000, $config->toArray()['max_request']);
    }

    #[Test]
    public function withDaemonizeChangesDaemonize(): void
    {
        $config = ServerConfig::default()->withDaemonize(true);

        self::assertTrue($config->toArray()['daemonize']);
    }

    #[Test]
    public function withLogFileIncludesLogFileWhenSet(): void
    {
        $config = ServerConfig::default()->withLogFile('/var/log/swoole.log');

        self::assertSame('/var/log/swoole.log', $config->toArray()['log_file']);
    }

    #[Test]
    public function withLogFileExcludesLogFileWhenEmpty(): void
    {
        $config = ServerConfig::default()->withLogFile('');

        self::assertArrayNotHasKey('log_file', $config->toArray());
    }

    #[Test]
    public function withSslCertFileIncludesSslCertFileWhenSet(): void
    {
        $config = ServerConfig::default()->withSslCertFile('/etc/ssl/cert.pem');

        self::assertSame('/etc/ssl/cert.pem', $config->toArray()['ssl_cert_file']);
    }

    #[Test]
    public function withStaticHandlerEnablesEnableStaticHandler(): void
    {
        $config = ServerConfig::default()->withStaticHandler(true);

        self::assertTrue($config->toArray()['enable_static_handler']);
    }

    #[Test]
    public function withDocumentRootIncludesDocumentRoot(): void
    {
        $config = ServerConfig::default()->withDocumentRoot('/var/www/public');

        self::assertSame('/var/www/public', $config->toArray()['document_root']);
    }

    #[Test]
    public function withStaticHandlerLocationsIncludesLocations(): void
    {
        $locations = ['/assets', '/images'];
        $config = ServerConfig::default()->withStaticHandlerLocations($locations);

        self::assertSame($locations, $config->toArray()['static_handler_locations']);
    }

    #[Test]
    public function withHttp2EnablesOpenHttp2Protocol(): void
    {
        $config = ServerConfig::default()->withHttp2(true);

        self::assertTrue($config->toArray()['open_http2_protocol']);
    }

    #[Test]
    public function withHttpCompressionSetsHttpCompression(): void
    {
        $config = ServerConfig::default()->withHttpCompression(false);

        self::assertFalse($config->toArray()['http_compression']);
    }

    #[Test]
    public function withHttpCompressionLevelSetsLevel(): void
    {
        $config = ServerConfig::default()->withHttpCompressionLevel(6);

        self::assertSame(6, $config->toArray()['http_compression_level']);
    }

    #[Test]
    public function withRawSettingsMergesSettingsTypedTakesPrecedence(): void
    {
        $config = ServerConfig::default()
            ->withWorkerNum(8)
            ->withRawSettings([
                'worker_num' => 2,
                'custom_setting' => 'value',
            ]);

        $array = $config->toArray();

        self::assertSame(8, $array['worker_num']);
        self::assertSame('value', $array['custom_setting']);
    }

    #[Test]
    public function getModeReturnsConfiguredMode(): void
    {
        $config = ServerConfig::default();
        self::assertSame(3, $config->getMode());

        $config = $config->withMode(1);
        self::assertSame(1, $config->getMode());
    }

    #[Test]
    public function getSockTypeReturnsConfiguredSockType(): void
    {
        $config = ServerConfig::default();
        self::assertSame(1, $config->getSockType());

        $config = $config->withSockType(6);
        self::assertSame(6, $config->getSockType());
    }

    #[Test]
    public function onWorkerStartAddsCallbackToGetCallbacks(): void
    {
        $callback = static function (): void {};
        $config = ServerConfig::default()->onWorkerStart($callback);

        $callbacks = $config->getCallbacks();

        self::assertCount(1, $callbacks['workerStart']);
        self::assertSame($callback, $callbacks['workerStart'][0]);
    }

    #[Test]
    public function onStartAddsCallback(): void
    {
        $callback = static function (): void {};
        $config = ServerConfig::default()->onStart($callback);

        $callbacks = $config->getCallbacks();

        self::assertCount(1, $callbacks['start']);
        self::assertSame($callback, $callbacks['start'][0]);
    }

    #[Test]
    public function onShutdownAddsCallback(): void
    {
        $callback = static function (): void {};
        $config = ServerConfig::default()->onShutdown($callback);

        $callbacks = $config->getCallbacks();

        self::assertCount(1, $callbacks['shutdown']);
        self::assertSame($callback, $callbacks['shutdown'][0]);
    }

    #[Test]
    public function multipleCallbacksForSameEventArePreserved(): void
    {
        $cb1 = static function (): void {};
        $cb2 = static function (): void {};
        $config = ServerConfig::default()
            ->onWorkerStart($cb1)
            ->onWorkerStart($cb2);

        $callbacks = $config->getCallbacks();

        self::assertCount(2, $callbacks['workerStart']);
        self::assertSame($cb1, $callbacks['workerStart'][0]);
        self::assertSame($cb2, $callbacks['workerStart'][1]);
    }

    #[Test]
    public function getCallbacksReturnsEmptyArraysForUnsetEvents(): void
    {
        $config = ServerConfig::default();
        $callbacks = $config->getCallbacks();

        self::assertSame([], $callbacks['start']);
        self::assertSame([], $callbacks['managerStart']);
        self::assertSame([], $callbacks['workerStart']);
        self::assertSame([], $callbacks['workerStop']);
        self::assertSame([], $callbacks['workerExit']);
        self::assertSame([], $callbacks['workerError']);
        self::assertSame([], $callbacks['beforeShutdown']);
        self::assertSame([], $callbacks['shutdown']);
    }

    #[Test]
    public function immutabilityWithMethodsReturnNewInstanceOriginalUnchanged(): void
    {
        $original = ServerConfig::default();
        $modified = $original->withWorkerNum(16);

        self::assertSame(swoole_cpu_num(), $original->toArray()['worker_num']);
        self::assertSame(16, $modified->toArray()['worker_num']);
        self::assertNotSame($original, $modified);
    }

    #[Test]
    public function immutabilityCallbacksDoNotMutateOriginal(): void
    {
        $original = ServerConfig::default();
        $modified = $original->onStart(static function (): void {});

        self::assertSame([], $original->getCallbacks()['start']);
        self::assertCount(1, $modified->getCallbacks()['start']);
    }

    #[Test]
    public function withPidFileIncludesPidFileWhenSet(): void
    {
        $config = ServerConfig::default()->withPidFile('/var/run/swoole.pid');

        self::assertSame('/var/run/swoole.pid', $config->toArray()['pid_file']);
    }

    #[Test]
    public function withPidFileExcludesPidFileWhenEmpty(): void
    {
        $config = ServerConfig::default()->withPidFile('');

        self::assertArrayNotHasKey('pid_file', $config->toArray());
    }
}
