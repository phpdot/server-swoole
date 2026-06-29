<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;

/**
 * Memory-regression guard: drive sustained load at a pinned worker and assert its emalloc heap
 * does not grow — i.e. the request/response path does not leak. Uses ApacheBench for the load and
 * is skipped where `ab` is unavailable, so it never breaks a runner that lacks it.
 *
 * Tolerance is deliberately generous (the observed growth is 0 bytes); this catches a real
 * per-request leak (bytes/req x tens of thousands = tens of MB), not allocator jitter.
 */
final class ServerMemoryTest extends ServerTestCase
{
    private const TOLERANCE_BYTES = 524288; // 512 KB

    protected function runnerScript(): string
    {
        return __DIR__ . '/Fixtures/server_mem_runner.php';
    }

    #[Test]
    public function requestPathDoesNotLeakUnderLoad(): void
    {
        if (trim((string) shell_exec('command -v ab')) === '') {
            self::markTestSkipped('ApacheBench (ab) is not available');
        }

        $target = escapeshellarg("http://127.0.0.1:{$this->port}/ok");

        // Warm up so buffers/opcode caches reach steady state, then take the post-warmup baseline.
        shell_exec("ab -n 3000 -c 20 -k -q {$target} 2>&1");
        $baseline = $this->heap();

        // Sustained concurrent load.
        shell_exec("ab -n 30000 -c 50 -k -q {$target} 2>&1");

        // Let in-flight teardown settle before measuring.
        usleep(1_500_000);
        $after = $this->heap();

        self::assertLessThanOrEqual(
            $baseline + self::TOLERANCE_BYTES,
            $after,
            sprintf('worker heap grew under load: %d -> %d bytes (delta %d)', $baseline, $after, $after - $baseline),
        );
    }

    private function heap(): int
    {
        $response = $this->rawRequest("GET /mem HTTP/1.1\r\nHost: x\r\nConnection: close\r\n\r\n");
        $decoded = json_decode($this->bodyOf($response), true);
        self::assertIsArray($decoded, 'malformed /mem response');

        return (int) ($decoded['heap'] ?? -1);
    }
}
