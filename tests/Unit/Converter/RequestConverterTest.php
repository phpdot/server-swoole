<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Unit\Converter;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Server\Swoole\Converter\RequestConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UploadedFileInterface;

final class RequestConverterTest extends TestCase
{
    private RequestConverter $converter;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->converter = new RequestConverter($factory, $factory, $factory, $factory);
    }

    #[Test]
    public function basicGetRequestAssemblesCorrectly(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/users/42'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('GET', $request->getMethod());
        self::assertSame('/users/42', $request->getUri()->getPath());
    }

    #[Test]
    public function postRequestWithBody(): void
    {
        $request = $this->converter->assembleRequest(
            headers: ['content-type' => 'application/json'],
            server: ['request_method' => 'post', 'request_uri' => '/users'],
            cookies: [],
            query: [],
            post: ['name' => 'John'],
            files: [],
            body: '{"name":"John"}',
        );

        self::assertSame('POST', $request->getMethod());
        self::assertSame('{"name":"John"}', (string) $request->getBody());
        self::assertSame(['name' => 'John'], $request->getParsedBody());
    }

    #[Test]
    public function headersAreSetOnThePsr7Request(): void
    {
        $request = $this->converter->assembleRequest(
            headers: ['authorization' => 'Bearer token123', 'accept' => 'application/json'],
            server: ['request_method' => 'get', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('Bearer token123', $request->getHeaderLine('authorization'));
        self::assertSame('application/json', $request->getHeaderLine('accept'));
    }

    #[Test]
    public function queryParamsAreSet(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/search', 'query_string' => 'q=test&page=2'],
            cookies: [],
            query: ['q' => 'test', 'page' => '2'],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame(['q' => 'test', 'page' => '2'], $request->getQueryParams());
    }

    #[Test]
    public function cookiesAreSet(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/'],
            cookies: ['session_id' => 'abc123', 'theme' => 'dark'],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame(['session_id' => 'abc123', 'theme' => 'dark'], $request->getCookieParams());
    }

    #[Test]
    public function parsedBodyPostDataIsSet(): void
    {
        $request = $this->converter->assembleRequest(
            headers: ['content-type' => 'application/x-www-form-urlencoded'],
            server: ['request_method' => 'post', 'request_uri' => '/login'],
            cookies: [],
            query: [],
            post: ['username' => 'admin', 'password' => 'secret'],
            files: [],
            body: 'username=admin&password=secret',
        );

        self::assertSame(['username' => 'admin', 'password' => 'secret'], $request->getParsedBody());
    }

    #[Test]
    public function protocolVersionExtractedFromServerProtocol(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/', 'server_protocol' => 'HTTP/2.0'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('2.0', $request->getProtocolVersion());
    }

    #[Test]
    public function defaultProtocolVersionIs11(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('1.1', $request->getProtocolVersion());
    }

    #[Test]
    public function uriSchemeDefaultsToHttp(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('http', $request->getUri()->getScheme());
    }

    #[Test]
    public function uriSchemeIsHttpsWhenServerHttpsIsOn(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/', 'https' => 'on'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('https', $request->getUri()->getScheme());
    }

    #[Test]
    public function hostFromHeadersHost(): void
    {
        $request = $this->converter->assembleRequest(
            headers: ['host' => 'example.com'],
            server: ['request_method' => 'get', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('example.com', $request->getUri()->getHost());
    }

    #[Test]
    public function hostFallbackToServerAddr(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/', 'server_addr' => '192.168.1.1'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('192.168.1.1', $request->getUri()->getHost());
    }

    #[Test]
    public function queryStringFromServerQueryString(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/search', 'query_string' => 'q=hello&lang=en'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('q=hello&lang=en', $request->getUri()->getQuery());
    }

    #[Test]
    public function serverParamsAreUppercased(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/', 'remote_addr' => '127.0.0.1'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        $serverParams = $request->getServerParams();

        self::assertArrayHasKey('REQUEST_METHOD', $serverParams);
        self::assertSame('get', $serverParams['REQUEST_METHOD']);
        self::assertArrayHasKey('REMOTE_ADDR', $serverParams);
        self::assertSame('127.0.0.1', $serverParams['REMOTE_ADDR']);
    }

    #[Test]
    public function contentTypeAddedToServerParamsFromHeaders(): void
    {
        $request = $this->converter->assembleRequest(
            headers: ['content-type' => 'application/json'],
            server: ['request_method' => 'post', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('application/json', $request->getServerParams()['CONTENT_TYPE']);
    }

    #[Test]
    public function contentLengthAddedToServerParamsFromHeaders(): void
    {
        $request = $this->converter->assembleRequest(
            headers: ['content-length' => '42'],
            server: ['request_method' => 'post', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('42', $request->getServerParams()['CONTENT_LENGTH']);
    }

    #[Test]
    public function singleFileUploadCreatesUploadedFileInterface(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        self::assertIsString($tmpFile);
        file_put_contents($tmpFile, 'file content');

        try {
            $request = $this->converter->assembleRequest(
                headers: [],
                server: ['request_method' => 'post', 'request_uri' => '/upload'],
                cookies: [],
                query: [],
                post: null,
                files: [
                    'avatar' => [
                        'tmp_name' => $tmpFile,
                        'size' => 12,
                        'error' => UPLOAD_ERR_OK,
                        'name' => 'photo.jpg',
                        'type' => 'image/jpeg',
                    ],
                ],
                body: '',
            );

            $uploadedFiles = $request->getUploadedFiles();
            self::assertArrayHasKey('avatar', $uploadedFiles);
            self::assertInstanceOf(UploadedFileInterface::class, $uploadedFiles['avatar']);
            self::assertSame(12, $uploadedFiles['avatar']->getSize());
            self::assertSame(UPLOAD_ERR_OK, $uploadedFiles['avatar']->getError());
            self::assertSame('photo.jpg', $uploadedFiles['avatar']->getClientFilename());
            self::assertSame('image/jpeg', $uploadedFiles['avatar']->getClientMediaType());
        } finally {
            @unlink($tmpFile);
        }
    }

    #[Test]
    public function multiFileUploadCreatesArrayOfUploadedFileInterface(): void
    {
        $tmpFile1 = tempnam(sys_get_temp_dir(), 'test_upload_');
        $tmpFile2 = tempnam(sys_get_temp_dir(), 'test_upload_');
        self::assertIsString($tmpFile1);
        self::assertIsString($tmpFile2);
        file_put_contents($tmpFile1, 'a');
        file_put_contents($tmpFile2, 'bb');

        try {
            $request = $this->converter->assembleRequest(
                headers: [],
                server: ['request_method' => 'post', 'request_uri' => '/upload'],
                cookies: [],
                query: [],
                post: null,
                files: [
                    'photos' => [
                        'tmp_name' => [$tmpFile1, $tmpFile2],
                        'size' => [1, 2],
                        'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                        'name' => ['a.jpg', 'b.jpg'],
                        'type' => ['image/jpeg', 'image/jpeg'],
                    ],
                ],
                body: '',
            );

            $uploadedFiles = $request->getUploadedFiles();
            self::assertArrayHasKey('photos', $uploadedFiles);
            self::assertIsArray($uploadedFiles['photos']);
            self::assertCount(2, $uploadedFiles['photos']);
            self::assertInstanceOf(UploadedFileInterface::class, $uploadedFiles['photos'][0]);
            self::assertInstanceOf(UploadedFileInterface::class, $uploadedFiles['photos'][1]);
            self::assertSame('a.jpg', $uploadedFiles['photos'][0]->getClientFilename());
            self::assertSame('b.jpg', $uploadedFiles['photos'][1]->getClientFilename());
        } finally {
            @unlink($tmpFile1);
            @unlink($tmpFile2);
        }
    }

    #[Test]
    public function emptyFilesArrayProducesEmptyUploadedFiles(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'post', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame([], $request->getUploadedFiles());
    }

    #[Test]
    public function emptyBodyProducesEmptyStream(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertSame('', (string) $request->getBody());
    }

    #[Test]
    public function nullPostProducesNullParsedBody(): void
    {
        $request = $this->converter->assembleRequest(
            headers: [],
            server: ['request_method' => 'get', 'request_uri' => '/'],
            cookies: [],
            query: [],
            post: null,
            files: [],
            body: '',
        );

        self::assertNull($request->getParsedBody());
    }
}
