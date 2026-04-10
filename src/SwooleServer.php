<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole;

use PHPdot\Server\Swoole\Config\ServerConfig;
use PHPdot\Server\Swoole\Converter\RequestConverter;
use PHPdot\Server\Swoole\Converter\ResponseConverter;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Http\Server;
use Throwable;

/**
 * SwooleServer.
 *
 * Framework-agnostic Swoole HTTP server that bridges PSR-15 request handlers
 * with the Swoole event loop. Converts between Swoole and PSR-7 messages
 * using the provided PSR-17 factories.
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

    /**
     * Create a new SwooleServer.
     *
     * @param ServerRequestFactoryInterface $serverRequestFactory Factory for creating server requests
     * @param UriFactoryInterface $uriFactory Factory for creating URIs
     * @param StreamFactoryInterface $streamFactory Factory for creating streams
     * @param UploadedFileFactoryInterface $uploadedFileFactory Factory for creating uploaded files
     */
    public function __construct(
        ServerRequestFactoryInterface $serverRequestFactory,
        UriFactoryInterface $uriFactory,
        StreamFactoryInterface $streamFactory,
        UploadedFileFactoryInterface $uploadedFileFactory,
    ) {
        $this->requestConverter = new RequestConverter(
            $serverRequestFactory,
            $uriFactory,
            $streamFactory,
            $uploadedFileFactory,
        );
        $this->responseConverter = new ResponseConverter();
    }

    /**
     * Create a SwooleServer with the default PSR-17 factories.
     */
    public static function create(): self
    {
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();

        return new self($factory, $factory, $factory, $factory);
    }

    /**
     * Create a SwooleServer from a single PSR-17 factory that implements all four interfaces.
     *
     * @param ServerRequestFactoryInterface&UriFactoryInterface&StreamFactoryInterface&UploadedFileFactoryInterface $factory Combined PSR-17 factory
     */
    public static function withPsr17(
        ServerRequestFactoryInterface&UriFactoryInterface&StreamFactoryInterface&UploadedFileFactoryInterface $factory,
    ): self {
        return new self($factory, $factory, $factory, $factory);
    }

    /**
     * Start the Swoole HTTP server and begin handling requests.
     *
     * Registers lifecycle callbacks and the request handler, then starts
     * the Swoole event loop. This method blocks until the server is shut down.
     *
     * @param RequestHandlerInterface $handler PSR-15 request handler
     * @param string $host Host address to bind to
     * @param int $port Port number to listen on
     * @param ServerConfig|null $config Server configuration, or null for defaults
     */
    public function serve(
        RequestHandlerInterface $handler,
        string $host = '0.0.0.0',
        int $port = 8080,
        ServerConfig|null $config = null,
    ): void {
        $config ??= ServerConfig::default();

        $server = new Server($host, $port, $config->getMode(), $config->getSockType());
        $server->set($config->toArray());

        $callbacks = $config->getCallbacks();
        foreach ($callbacks as $event => $eventCallbacks) {
            if ($eventCallbacks === []) {
                continue;
            }
            $server->on($event, static function () use ($eventCallbacks): void {
                /** @var list<mixed> $args */
                $args = func_get_args();
                foreach ($eventCallbacks as $callback) {
                    $callback(...$args);
                }
            });
        }

        $server->on('request', function (SwooleRequest $swooleRequest, SwooleResponse $swooleResponse) use ($handler): void {
            try {
                $psrRequest = $this->requestConverter->toServerRequest($swooleRequest);
                $psrResponse = $handler->handle($psrRequest);
                $this->responseConverter->toSwoole($psrResponse, $swooleResponse);
            } catch (Throwable) {
                $swooleResponse->status(500);
                $swooleResponse->end('Internal Server Error');
            }
        });

        $server->start();
    }
}
