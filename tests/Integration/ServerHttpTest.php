<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * End-to-end HTTP/SSE tests against a real SwooleServer process (Fixtures/server_runner.php),
 * driven over raw TCP so the exact bytes on the wire can be asserted.
 */
final class ServerHttpTest extends ServerTestCase
{
    protected function runnerScript(): string
    {
        return __DIR__ . '/Fixtures/server_runner.php';
    }

    #[Test]
    public function headRequestReturnsNoBody(): void
    {
        // Sanity: GET returns the body.
        $get = $this->rawRequest("GET /head-body HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");
        self::assertStringContainsString('200', $this->statusLine($get));
        self::assertStringContainsString('HELLO-BODY-1234567890', $this->bodyOf($get), 'GET should return the body');

        // HEAD must return headers but NO body (RFC 7231). On keep-alive a body here desyncs the stream.
        $head = $this->rawRequest("HEAD /head-body HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");
        self::assertStringContainsString('200', $this->statusLine($head));
        self::assertSame('', $this->bodyOf($head), 'HEAD response must not include a body');
    }

    #[Test]
    public function midStreamExceptionDoesNotLeakErrorMessage(): void
    {
        // The body writes one chunk then throws. The response has already started, so the
        // adapter must NOT append the raw exception message to the wire.
        $response = $this->rawRequest("GET /stream-throw HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");

        self::assertStringNotContainsString(
            'BOOM_SECRET_42',
            $response,
            'internal exception message must not be appended to an already-started response',
        );
    }

    #[Test]
    public function exceptionBeforeWriteStillReturns500(): void
    {
        // Not-started branch: a throw before any bytes are written produces a real 500. The raw
        // exception message is passed through to the client by design — sanitizing/handling errors
        // is the application's responsibility (e.g. phpdot/error-handler), not the server adapter's.
        $response = $this->rawRequest("GET /throw-before-write HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");

        self::assertStringContainsString('500', $this->statusLine($response));
    }

    #[Test]
    public function getReturnsBodyAndHeaders(): void
    {
        $response = $this->rawRequest("GET /ok HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");

        self::assertStringContainsString('200', $this->statusLine($response));
        self::assertStringContainsStringIgnoringCase('Content-Type: text/plain', $response);
        self::assertSame('OK', $this->bodyOf($response));
    }

    #[Test]
    public function postBodyRoundTrips(): void
    {
        $body = 'hello=world&n=42';
        $response = $this->rawRequest(
            "POST /echo-body HTTP/1.1\r\nHost: x\r\nContent-Type: application/x-www-form-urlencoded\r\n"
            . 'Content-Length: ' . strlen($body) . "\r\nConnection: close\r\n\r\n" . $body,
        );

        self::assertSame($body, $this->bodyOf($response), 'the raw POST body should reach the handler');
    }

    #[Test]
    public function queryHeaderAndCookieReachTheHandler(): void
    {
        $response = $this->rawRequest(
            "GET /echo-request?name=bob HTTP/1.1\r\nHost: x\r\nX-Test: abc\r\nCookie: sid=xyz\r\nConnection: close\r\n\r\n",
        );

        self::assertSame('m=GET;q=bob;h=abc;c=xyz', $this->bodyOf($response));
    }

    #[Test]
    public function responseCookieIsEmitted(): void
    {
        $response = $this->rawRequest("GET /set-cookie HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");

        self::assertMatchesRegularExpression('/Set-Cookie:\s*sid=abc123/i', $response);
        self::assertStringContainsStringIgnoringCase('HttpOnly', $this->headerLine($response, 'Set-Cookie: sid='));
    }

    #[Test]
    public function sessionCookieHasNoExpiry(): void
    {
        $response = $this->rawRequest("GET /set-session-cookie HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");

        $line = $this->headerLine($response, 'Set-Cookie: sess=');
        self::assertNotSame('', $line, 'session cookie should be emitted');
        self::assertStringNotContainsStringIgnoringCase('Expires=', $line);
        self::assertStringNotContainsStringIgnoringCase('Max-Age=', $line);
    }

    #[Test]
    public function largeResponseStreamsChunked(): void
    {
        $response = $this->rawRequest("GET /large HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");

        self::assertStringContainsStringIgnoringCase('Transfer-Encoding: chunked', $response);
        self::assertSame(1_500_000, substr_count($response, 'Z'), 'all body bytes should arrive');
    }

    #[Test]
    public function customStatusAndHeaderArePreserved(): void
    {
        $response = $this->rawRequest("GET /created HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");

        self::assertStringContainsString('201', $this->statusLine($response));
        self::assertMatchesRegularExpression('/X-Request-Id:\s*r-123/i', $response);
        self::assertSame('made', $this->bodyOf($response));
    }

    #[Test]
    public function sseStreamsEvents(): void
    {
        $response = $this->rawRequest(
            "GET /sse HTTP/1.1\r\nHost: x\r\nAccept: text/event-stream\r\nConnection: close\r\n\r\n",
        );

        self::assertStringContainsStringIgnoringCase('Content-Type: text/event-stream', $response);
        self::assertStringContainsString('data: e1', $response);
        self::assertStringContainsString('data: e2', $response);
    }
}
