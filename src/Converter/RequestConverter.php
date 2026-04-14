<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Converter;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;
use Swoole\Http\Request as SwooleRequest;

/**
 * RequestConverter.
 *
 * Converts a Swoole HTTP request into a PSR-7 ServerRequestInterface.
 * The conversion is split into extraction and assembly steps so the
 * assembly logic can be tested with plain arrays.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class RequestConverter
{
    /**
     * Create a new RequestConverter.
     *
     * @param \Psr\Http\Message\ServerRequestFactoryInterface $serverRequestFactory Factory for creating server requests
     * @param \Psr\Http\Message\UriFactoryInterface $uriFactory Factory for creating URIs
     * @param \Psr\Http\Message\StreamFactoryInterface $streamFactory Factory for creating streams
     * @param \Psr\Http\Message\UploadedFileFactoryInterface $uploadedFileFactory Factory for creating uploaded files
     */
    public function __construct(
        private readonly \Psr\Http\Message\ServerRequestFactoryInterface $serverRequestFactory,
        private readonly \Psr\Http\Message\UriFactoryInterface $uriFactory,
        private readonly \Psr\Http\Message\StreamFactoryInterface $streamFactory,
        private readonly \Psr\Http\Message\UploadedFileFactoryInterface $uploadedFileFactory,
    ) {}

    /**
     * Convert a Swoole HTTP request to a PSR-7 server request.
     *
     * @param SwooleRequest $swooleRequest The Swoole request to convert
     */
    public function toServerRequest(SwooleRequest $swooleRequest): ServerRequestInterface
    {
        /** @var array<string, string> $headers */
        $headers = $swooleRequest->header ?? [];
        /** @var array<string, string> $server */
        $server = $swooleRequest->server ?? [];
        /** @var array<string, string> $cookies */
        $cookies = $swooleRequest->cookie ?? [];
        /** @var array<string, mixed> $query */
        $query = $swooleRequest->get ?? [];
        /** @var array<string, mixed>|null $post */
        $post = $swooleRequest->post;
        /** @var array<string, mixed> $files */
        $files = $swooleRequest->files ?? [];
        $rawContent = $swooleRequest->rawContent();
        $body = $rawContent === false ? '' : $rawContent;

        return $this->assembleRequest($headers, $server, $cookies, $query, $post, $files, $body);
    }

    /**
     * Assemble a PSR-7 request from raw arrays. Public for testing.
     *
     * @param array<string, string> $headers HTTP headers (lowercase keys from Swoole)
     * @param array<string, string> $server Server variables (lowercase keys from Swoole)
     * @param array<string, string> $cookies Cookie parameters
     * @param array<string, mixed> $query Query string parameters
     * @param array<string, mixed>|null $post Parsed POST body, or null if not a POST request
     * @param array<string, mixed> $files Uploaded files array
     * @param string $body Raw request body
     */
    public function assembleRequest(
        array $headers,
        array $server,
        array $cookies,
        array $query,
        array|null $post,
        array $files,
        string $body,
    ): ServerRequestInterface {
        $uri = $this->buildUri($headers, $server);

        $serverParams = $this->buildServerParams($headers, $server);

        $method = strtoupper($server['request_method'] ?? 'GET');
        $request = $this->serverRequestFactory->createServerRequest($method, $uri, $serverParams);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        $protocol = $server['server_protocol'] ?? 'HTTP/1.1';
        $version = str_replace('HTTP/', '', $protocol);
        $request = $request->withProtocolVersion($version);

        $request = $request->withCookieParams($cookies);
        $request = $request->withQueryParams($query);
        $request = $request->withBody($this->streamFactory->createStream($body));

        if ($post !== null) {
            $request = $request->withParsedBody($post);
        }

        $normalizedFiles = $this->normalizeFiles($files);
        if ($normalizedFiles !== []) {
            $request = $request->withUploadedFiles($normalizedFiles);
        }

        return $request;
    }

    /**
     * Build a PSR-7 URI from headers and server variables.
     *
     * @param array<string, string> $headers HTTP headers
     * @param array<string, string> $server Server variables
     */
    private function buildUri(array $headers, array $server): UriInterface
    {
        $scheme = (isset($server['https']) && $server['https'] === 'on') ? 'https' : 'http';

        if (isset($headers['host'])) {
            $host = $headers['host'];
        } else {
            $addr = $server['server_addr'] ?? 'localhost';
            $port = $server['server_port'] ?? '';
            $isDefaultPort = ($scheme === 'http' && $port === '80')
                || ($scheme === 'https' && $port === '443');
            if ($port !== '' && !$isDefaultPort) {
                $host = $addr . ':' . $port;
            } else {
                $host = $addr;
            }
        }
        $path = explode('?', $server['request_uri'] ?? '/')[0];
        $queryString = $server['query_string'] ?? '';

        $uriString = $scheme . '://' . $host . $path;
        if ($queryString !== '') {
            $uriString .= '?' . $queryString;
        }

        return $this->uriFactory->createUri($uriString);
    }

    /**
     * Build uppercased server params from Swoole's lowercase keys.
     *
     * @param array<string, string> $headers HTTP headers
     * @param array<string, string> $server Server variables
     * @return array<string, string>
     */
    private function buildServerParams(array $headers, array $server): array
    {
        $serverParams = [];
        foreach ($server as $key => $value) {
            $serverParams[strtoupper($key)] = $value;
        }

        foreach ($headers as $name => $value) {
            $upperName = strtoupper(str_replace('-', '_', $name));
            if ($upperName === 'CONTENT_TYPE' || $upperName === 'CONTENT_LENGTH') {
                $serverParams[$upperName] = $value;
            } else {
                $serverParams['HTTP_' . $upperName] = $value;
            }
        }

        return $serverParams;
    }

    /**
     * Normalize uploaded files into UploadedFileInterface instances.
     *
     * @param array<string, mixed> $files Raw files array from Swoole
     * @return array<string, UploadedFileInterface|array<int, UploadedFileInterface>>
     */
    private function normalizeFiles(array $files): array
    {
        $normalized = [];
        foreach ($files as $key => $file) {
            if (!is_array($file) || !isset($file['tmp_name'])) {
                continue;
            }
            /** @var array<string, mixed> $fileArray */
            $fileArray = $file;
            if (is_array($fileArray['tmp_name'])) {
                $normalized[$key] = $this->normalizeMultiFile($fileArray);
            } else {
                $normalized[$key] = $this->createUploadedFile($fileArray);
            }
        }
        return $normalized;
    }

    /**
     * Normalize a multi-file upload into an array of UploadedFileInterface instances.
     *
     * @param array<string, mixed> $file Multi-file upload array
     * @return array<int, UploadedFileInterface>
     */
    private function normalizeMultiFile(array $file): array
    {
        $files = [];
        /** @var array<int, string> $tmpNames */
        $tmpNames = $file['tmp_name'];
        /** @var array<int, int> $sizes */
        $sizes = $file['size'] ?? [];
        /** @var array<int, int> $errors */
        $errors = $file['error'] ?? [];
        /** @var array<int, string> $names */
        $names = $file['name'] ?? [];
        /** @var array<int, string> $types */
        $types = $file['type'] ?? [];
        foreach (array_keys($tmpNames) as $index) {
            $files[$index] = $this->createUploadedFile([
                'tmp_name' => $tmpNames[$index],
                'size' => $sizes[$index] ?? 0,
                'error' => $errors[$index] ?? UPLOAD_ERR_NO_FILE,
                'name' => $names[$index] ?? '',
                'type' => $types[$index] ?? '',
            ]);
        }
        return $files;
    }

    /**
     * Create an UploadedFileInterface instance from a file array.
     *
     * @param array<string, mixed> $file Single file upload array
     */
    private function createUploadedFile(array $file): UploadedFileInterface
    {
        /** @var string $tmpName */
        $tmpName = $file['tmp_name'];
        /** @var int $size */
        $size = $file['size'] ?? 0;
        /** @var int $error */
        $error = $file['error'] ?? UPLOAD_ERR_OK;
        /** @var string $name */
        $name = $file['name'] ?? '';
        /** @var string $type */
        $type = $file['type'] ?? '';

        if ($error !== UPLOAD_ERR_OK || $tmpName === '') {
            $stream = $this->streamFactory->createStream('');
        } else {
            $stream = $this->streamFactory->createStreamFromFile($tmpName);
        }

        return $this->uploadedFileFactory->createUploadedFile(
            $stream,
            $size,
            $error,
            $name,
            $type,
        );
    }
}
