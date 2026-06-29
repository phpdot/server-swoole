<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base for end-to-end tests that boot a real SwooleServer in a separate process
 * and drive it over raw TCP. Subclasses point runnerScript() at the fixture to launch.
 */
abstract class ServerTestCase extends TestCase
{
    /** @var resource|null */
    private $process = null;

    protected int $port = 0;

    private string $logFile = '';

    /**
     * Absolute path to the server runner script this test boots.
     */
    abstract protected function runnerScript(): string;

    protected function setUp(): void
    {
        $this->port = $this->findFreePort();
        $this->logFile = sys_get_temp_dir() . '/swoole_it_' . getmypid() . '_' . $this->port . '.log';

        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($this->runnerScript()) . ' ' . $this->port;

        // Descriptors go to FILES, never to unread pipes (a full pipe buffer would hang the server).
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', $this->logFile, 'w'],
            2 => ['file', $this->logFile, 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);
        self::assertIsResource($process, 'failed to launch server runner');
        $this->process = $process;

        $this->waitForServer();
    }

    protected function tearDown(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process, SIGTERM);

            $deadline = microtime(true) + 3.0;
            while (microtime(true) < $deadline) {
                if (proc_get_status($this->process)['running'] === false) {
                    break;
                }
                usleep(50_000);
            }

            if (proc_get_status($this->process)['running'] === true) {
                proc_terminate($this->process, SIGKILL);
            }

            proc_close($this->process);
            $this->process = null;
        }

        if ($this->logFile !== '' && is_file($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    protected function findFreePort(): int
    {
        $sock = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertIsResource($sock, "could not allocate a free port: {$errstr}");
        $name = stream_socket_get_name($sock, false);
        self::assertIsString($name);
        $port = (int) substr($name, (int) strrpos($name, ':') + 1);
        fclose($sock);

        return $port;
    }

    protected function waitForServer(): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $fp = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.2);
            if (is_resource($fp)) {
                fclose($fp);

                return;
            }

            if (is_resource($this->process) && proc_get_status($this->process)['running'] === false) {
                self::fail("server exited before becoming ready:\n" . (string) @file_get_contents($this->logFile));
            }

            usleep(100_000);
        }

        self::fail("server did not become ready in time:\n" . (string) @file_get_contents($this->logFile));
    }

    protected function rawRequest(string $raw): string
    {
        $fp = fsockopen('127.0.0.1', $this->port, $errno, $errstr, 5.0);
        self::assertIsResource($fp, "connect failed: {$errstr}");
        stream_set_timeout($fp, 5);

        fwrite($fp, $raw);

        $response = '';
        while (feof($fp) === false) {
            $chunk = fread($fp, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $response .= $chunk;
            if (stream_get_meta_data($fp)['timed_out'] === true) {
                break;
            }
        }
        fclose($fp);

        return $response;
    }

    protected function statusLine(string $response): string
    {
        $pos = strpos($response, "\r\n");

        return $pos === false ? $response : substr($response, 0, $pos);
    }

    protected function bodyOf(string $response): string
    {
        $parts = explode("\r\n\r\n", $response, 2);

        return $parts[1] ?? '';
    }

    protected function headerLine(string $response, string $needle): string
    {
        $headers = explode("\r\n\r\n", $response, 2)[0];
        foreach (explode("\r\n", $headers) as $line) {
            if (stripos($line, $needle) !== false) {
                return $line;
            }
        }

        return '';
    }
}
