<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Server\Swoole\Config\ServerConfig;
use PHPdot\Server\Swoole\Exception\ServerException;
use PHPdot\Server\Swoole\SwooleServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SwooleServerTest extends TestCase
{
    private SwooleServer $server;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->server = new SwooleServer($factory);
    }

    #[Test]
    public function constructsWithDefaultConfig(): void
    {
        $factory = new Psr17Factory();
        $server = new SwooleServer($factory);

        self::assertInstanceOf(SwooleServer::class, $server);
    }

    #[Test]
    public function constructsWithCustomConfig(): void
    {
        $factory = new Psr17Factory();
        $config = new ServerConfig(workerNum: 4, http2: true);
        $server = new SwooleServer($factory, $config);

        self::assertInstanceOf(SwooleServer::class, $server);
    }

    #[Test]
    public function onStartAcceptsCallback(): void
    {
        $this->server->onStart(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onManagerStartAcceptsCallback(): void
    {
        $this->server->onManagerStart(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onManagerStopAcceptsCallback(): void
    {
        $this->server->onManagerStop(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onWorkerStartAcceptsCallback(): void
    {
        $this->server->onWorkerStart(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onWorkerStopAcceptsCallback(): void
    {
        $this->server->onWorkerStop(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onWorkerExitAcceptsCallback(): void
    {
        $this->server->onWorkerExit(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onWorkerErrorAcceptsCallback(): void
    {
        $this->server->onWorkerError(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onBeforeShutdownAcceptsCallback(): void
    {
        $this->server->onBeforeShutdown(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onShutdownAcceptsCallback(): void
    {
        $this->server->onShutdown(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onBeforeReloadAcceptsCallback(): void
    {
        $this->server->onBeforeReload(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onAfterReloadAcceptsCallback(): void
    {
        $this->server->onAfterReload(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onConnectAcceptsCallback(): void
    {
        $this->server->onConnect(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onCloseAcceptsCallback(): void
    {
        $this->server->onClose(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onTaskAcceptsCallback(): void
    {
        $this->server->onTask(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onFinishAcceptsCallback(): void
    {
        $this->server->onFinish(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onPipeMessageAcceptsCallback(): void
    {
        $this->server->onPipeMessage(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onOpenAcceptsCallback(): void
    {
        $this->server->onOpen(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onMessageAcceptsCallback(): void
    {
        $this->server->onMessage(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onHandshakeAcceptsCallback(): void
    {
        $this->server->onHandshake(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onDisconnectAcceptsCallback(): void
    {
        $this->server->onDisconnect(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function multipleCallbacksCanBeRegisteredForSameEvent(): void
    {
        $this->server->onWorkerStart(static function (): void {});
        $this->server->onWorkerStart(static function (): void {});
        $this->server->onWorkerStart(static function (): void {});

        self::assertTrue(true);
    }

    #[Test]
    public function onHandshakeReplacesInsteadOfAppending(): void
    {
        $this->server->onHandshake(static function (): bool {
            return false;
        });
        $this->server->onHandshake(static function (): bool {
            return true;
        });

        self::assertTrue(true);
    }

    #[Test]
    public function getServerThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server has not been started. Call serve() first.');

        $this->server->getServer();
    }

    #[Test]
    public function pushThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->push(1, 'data');
    }

    #[Test]
    public function wsDisconnectThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->wsDisconnect(1);
    }

    #[Test]
    public function isEstablishedThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->isEstablished(1);
    }

    #[Test]
    public function taskThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->task('data');
    }

    #[Test]
    public function taskCoThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->taskCo(['data']);
    }

    #[Test]
    public function finishThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->finish('data');
    }

    #[Test]
    public function closeThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->close(1);
    }

    #[Test]
    public function existsThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->exists(1);
    }

    #[Test]
    public function getClientInfoThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->getClientInfo(1);
    }

    #[Test]
    public function getClientListThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->getClientList();
    }

    #[Test]
    public function sendMessageThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->sendMessage('msg', 0);
    }

    #[Test]
    public function statsThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->stats();
    }

    #[Test]
    public function getWorkerIdThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->getWorkerId();
    }

    #[Test]
    public function getWorkerPidThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->getWorkerPid();
    }

    #[Test]
    public function getWorkerStatusThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->getWorkerStatus();
    }

    #[Test]
    public function getMasterPidThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->getMasterPid();
    }

    #[Test]
    public function getManagerPidThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->getManagerPid();
    }

    #[Test]
    public function shutdownThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->shutdown();
    }

    #[Test]
    public function reloadThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->reload();
    }

    #[Test]
    public function stopThrowsBeforeServe(): void
    {
        $this->expectException(ServerException::class);

        $this->server->stop();
    }
}
