<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * End-to-end WebSocket tests against a real SwooleServer process (Fixtures/server_ws_runner.php).
 * Speaks RFC 6455 over a raw socket: HTTP upgrade handshake, a masked client text frame out, and
 * the server's unmasked echo frame back.
 */
final class ServerWebSocketTest extends ServerTestCase
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    protected function runnerScript(): string
    {
        return __DIR__ . '/Fixtures/server_ws_runner.php';
    }

    #[Test]
    public function handshakeUpgradesAndEchoesMessage(): void
    {
        $socket = fsockopen('127.0.0.1', $this->port, $errno, $errstr, 5.0);
        self::assertIsResource($socket, "connect failed: {$errstr}");
        stream_set_timeout($socket, 5);

        $key = base64_encode(random_bytes(16));
        fwrite(
            $socket,
            "GET /ws HTTP/1.1\r\nHost: 127.0.0.1\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\n\r\n",
        );

        $handshake = $this->readHandshake($socket);
        self::assertStringContainsString('101', $handshake, 'expected 101 Switching Protocols');

        $accept = base64_encode(sha1($key . self::GUID, true));
        self::assertStringContainsStringIgnoringCase("Sec-WebSocket-Accept: {$accept}", $handshake);

        fwrite($socket, $this->encodeMaskedTextFrame('ping'));
        self::assertSame('echo:ping', $this->readTextFrame($socket));

        fclose($socket);
    }

    /**
     * @param resource $socket
     */
    private function readHandshake($socket): string
    {
        $buffer = '';
        while (!str_contains($buffer, "\r\n\r\n")) {
            $byte = fread($socket, 1);
            if ($byte === '' || $byte === false || stream_get_meta_data($socket)['timed_out'] === true) {
                break;
            }
            $buffer .= $byte;
        }

        return $buffer;
    }

    private function encodeMaskedTextFrame(string $payload): string
    {
        $length = strlen($payload);
        $mask = random_bytes(4);

        $frame = chr(0x81); // FIN + text opcode
        if ($length < 126) {
            $frame .= chr(0x80 | $length);
        } else {
            $frame .= chr(0x80 | 126) . pack('n', $length);
        }
        $frame .= $mask;

        for ($i = 0; $i < $length; $i++) {
            $frame .= $payload[$i] ^ $mask[$i % 4];
        }

        return $frame;
    }

    /**
     * @param resource $socket
     */
    private function readTextFrame($socket): string
    {
        $header = $this->readBytes($socket, 2);
        self::assertSame(2, strlen($header), 'incomplete WebSocket frame header');

        $length = ord($header[1]) & 0x7F; // server frames are not masked
        if ($length === 126) {
            $extended = unpack('n', $this->readBytes($socket, 2));
            $length = is_array($extended) ? (int) $extended[1] : 0;
        }

        return $length === 0 ? '' : $this->readBytes($socket, $length);
    }

    /**
     * @param resource $socket
     */
    private function readBytes($socket, int $count): string
    {
        $buffer = '';
        while (strlen($buffer) < $count) {
            $chunk = fread($socket, $count - strlen($buffer));
            if ($chunk === '' || $chunk === false || stream_get_meta_data($socket)['timed_out'] === true) {
                break;
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }
}
