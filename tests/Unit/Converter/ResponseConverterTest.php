<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Unit\Converter;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPdot\Http\Factory\ResponseFactory;
use PHPdot\Server\Swoole\Converter\ResponseConverter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Swoole\Http\Response as SwooleResponse;

final class ResponseConverterTest extends TestCase
{
    private ResponseConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new ResponseConverter();
    }

    #[Test]
    public function parsesSimpleCookie(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123');

        self::assertSame('session', $result['name']);
        self::assertSame('abc123', $result['value']);
    }

    #[Test]
    public function parsesCookieWithPath(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123; Path=/');

        self::assertSame('session', $result['name']);
        self::assertSame('abc123', $result['value']);
        self::assertSame('/', $result['path']);
    }

    #[Test]
    public function parsesCookieWithDomain(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123; Domain=.example.com');

        self::assertSame('.example.com', $result['domain']);
    }

    #[Test]
    public function parsesCookieWithSecureFlag(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123; Secure');

        self::assertTrue($result['secure']);
    }

    #[Test]
    public function parsesCookieWithHttpOnlyFlag(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123; HttpOnly');

        self::assertTrue($result['httpOnly']);
    }

    #[Test]
    public function parsesCookieWithSameSiteStrict(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123; SameSite=Strict');

        self::assertSame('Strict', $result['sameSite']);
    }

    #[Test]
    public function parsesCookieWithSameSiteLax(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123; SameSite=Lax');

        self::assertSame('Lax', $result['sameSite']);
    }

    #[Test]
    public function parsesCookieWithSameSiteNone(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123; SameSite=None');

        self::assertSame('None', $result['sameSite']);
    }

    #[Test]
    public function parsesCookieWithMaxAge(): void
    {
        $before = time();
        $result = $this->converter->parseCookieHeader('session=abc123; Max-Age=3600');
        $after = time();

        self::assertGreaterThanOrEqual($before + 3600, $result['expires']);
        self::assertLessThanOrEqual($after + 3600, $result['expires']);
    }

    #[Test]
    public function parsesCookieWithExpires(): void
    {
        $dateString = 'Thu, 01 Dec 2025 16:00:00 GMT';
        $expected = strtotime($dateString);

        $result = $this->converter->parseCookieHeader('session=abc123; Expires=' . $dateString);

        self::assertSame($expected, $result['expires']);
    }

    #[Test]
    public function parsesCookieWithPartitionedFlag(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc123; Partitioned');

        self::assertTrue($result['partitioned']);
    }

    #[Test]
    public function parsesFullCookieWithAllAttributes(): void
    {
        $header = 'session=abc123; Path=/app; Domain=.example.com; Secure; HttpOnly; SameSite=Strict; Partitioned';
        $result = $this->converter->parseCookieHeader($header);

        self::assertSame('session', $result['name']);
        self::assertSame('abc123', $result['value']);
        self::assertSame('/app', $result['path']);
        self::assertSame('.example.com', $result['domain']);
        self::assertTrue($result['secure']);
        self::assertTrue($result['httpOnly']);
        self::assertSame('Strict', $result['sameSite']);
        self::assertTrue($result['partitioned']);
    }

    #[Test]
    public function parsesCookieWithEmptyValue(): void
    {
        $result = $this->converter->parseCookieHeader('session=');

        self::assertSame('session', $result['name']);
        self::assertSame('', $result['value']);
    }

    #[Test]
    public function caseInsensitiveAttributeParsing(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc; path=/test; domain=.example.com; secure; httponly; samesite=Lax');

        self::assertSame('/test', $result['path']);
        self::assertSame('.example.com', $result['domain']);
        self::assertTrue($result['secure']);
        self::assertTrue($result['httpOnly']);
        self::assertSame('Lax', $result['sameSite']);
    }

    #[Test]
    public function defaultValuesForMissingAttributes(): void
    {
        $result = $this->converter->parseCookieHeader('session=abc');

        self::assertSame(0, $result['expires']);
        self::assertSame('/', $result['path']);
        self::assertSame('', $result['domain']);
        self::assertFalse($result['secure']);
        self::assertFalse($result['httpOnly']);
        self::assertSame('', $result['sameSite']);
        self::assertFalse($result['partitioned']);
    }

    #[Test]
    public function appliesServerSoftwareWhenResponseHasNoServerHeader(): void
    {
        $converter = new ResponseConverter(serverSoftware: 'PHPdot Server');
        $response = (new Psr17Factory())->createResponse(200);

        $captured = $this->captureHeaders($converter, $response);

        self::assertSame('PHPdot Server', $captured['Server'] ?? null);
    }

    #[Test]
    public function keepsResponseServerHeaderOverConfiguredDefault(): void
    {
        $converter = new ResponseConverter(serverSoftware: 'PHPdot Server');
        $response = (new Psr17Factory())->createResponse(200)->withHeader('Server', 'Custom/1.0');

        $captured = $this->captureHeaders($converter, $response);

        self::assertSame('Custom/1.0', $captured['Server'] ?? null);
    }

    #[Test]
    public function appliesNoServerHeaderWhenSoftwareIsEmpty(): void
    {
        $converter = new ResponseConverter(serverSoftware: '');
        $response = (new Psr17Factory())->createResponse(200);

        $captured = $this->captureHeaders($converter, $response);

        self::assertArrayNotHasKey('Server', $captured);
    }

    #[Test]
    public function streamedResponseIsEmittedViaWrite(): void
    {
        $response = (new ResponseFactory())->stream(static function (callable $write): void {
            $write('chunk-1');
            $write('chunk-2');
        }, 200, ['Content-Type' => 'text/plain']);

        $chunks = [];
        $swoole = $this->createMock(SwooleResponse::class);
        $swoole->method('write')->willReturnCallback(
            static function (string $chunk) use (&$chunks): bool {
                $chunks[] = $chunk;

                return true;
            },
        );
        $swoole->expects(self::once())->method('end');

        $this->converter->toSwoole($response, $swoole);

        self::assertSame(['chunk-1', 'chunk-2'], $chunks);
    }

    /**
     * @return array<string, string>
     */
    private function captureHeaders(ResponseConverter $converter, ResponseInterface $response): array
    {
        $captured = [];

        $swoole = $this->createMock(SwooleResponse::class);
        $swoole->method('header')->willReturnCallback(
            function (string $name, string $value) use (&$captured): bool {
                $captured[$name] = $value;

                return true;
            },
        );

        $converter->toSwoole($response, $swoole);

        return $captured;
    }
}
