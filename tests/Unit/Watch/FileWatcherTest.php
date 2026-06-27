<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Unit\Watch;

use PHPdot\Server\Swoole\Contract\WatcherInterface;
use PHPdot\Server\Swoole\Enum\WatchAction;
use PHPdot\Server\Swoole\Watch\FileWatcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileWatcherTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/fw_' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rmrf($this->dir);
    }

    #[Test]
    public function snapshotCapturesMatchingFilesRecursively(): void
    {
        $this->write('a.php', 100);
        $this->write('sub/b.php', 200);
        $this->write('c.txt', 300);

        $snapshot = (new FileWatcher($this->watcher([$this->dir])))->snapshot();

        self::assertArrayHasKey($this->dir . '/a.php', $snapshot);
        self::assertArrayHasKey($this->dir . '/sub/b.php', $snapshot);
        self::assertArrayNotHasKey($this->dir . '/c.txt', $snapshot);
    }

    #[Test]
    public function depthZeroStaysFlat(): void
    {
        $this->write('a.php', 100);
        $this->write('sub/b.php', 200);

        $snapshot = (new FileWatcher($this->watcher([$this->dir], depth: 0)))->snapshot();

        self::assertArrayHasKey($this->dir . '/a.php', $snapshot);
        self::assertArrayNotHasKey($this->dir . '/sub/b.php', $snapshot);
    }

    #[Test]
    public function planClassifiesReloadAndRestart(): void
    {
        $this->write('protected/x.php', 100);
        $this->write('config/y.php', 100);

        $fileWatcher = new FileWatcher($this->watcher(
            [$this->dir . '/protected', $this->dir . '/config'],
            restart: [$this->dir . '/config'],
        ));
        $previous = $fileWatcher->snapshot();

        $this->write('protected/x.php', 200);
        $this->write('config/y.php', 200);

        $plan = $fileWatcher->plan($previous);

        self::assertSame([$this->dir . '/protected/x.php'], $plan['reload']);
        self::assertSame([$this->dir . '/config/y.php'], $plan['restart']);
    }

    #[Test]
    public function planDetectsNewAndDeletedFiles(): void
    {
        $this->write('a.php', 100);
        $fileWatcher = new FileWatcher($this->watcher([$this->dir]));
        $previous = $fileWatcher->snapshot();

        $this->write('new.php', 200);
        unlink($this->dir . '/a.php');

        $plan = $fileWatcher->plan($previous);

        self::assertContains($this->dir . '/new.php', $plan['reload']);
        self::assertContains($this->dir . '/a.php', $plan['reload']);
        self::assertCount(2, $plan['reload']);
    }

    #[Test]
    public function planIsEmptyWhenNothingChanged(): void
    {
        $this->write('a.php', 100);
        $fileWatcher = new FileWatcher($this->watcher([$this->dir]));
        $previous = $fileWatcher->snapshot();

        $plan = $fileWatcher->plan($previous);

        self::assertSame([], $plan['reload']);
        self::assertSame([], $plan['restart']);
    }

    #[Test]
    public function snapshotSkipsExcludedDirectories(): void
    {
        $this->write('a.php', 100);
        $this->write('vendor/lib.php', 100);

        $snapshot = (new FileWatcher($this->watcher([$this->dir], excludes: ['vendor'])))->snapshot();

        self::assertArrayHasKey($this->dir . '/a.php', $snapshot);
        self::assertArrayNotHasKey($this->dir . '/vendor/lib.php', $snapshot);
    }

    #[Test]
    public function planSkipsIgnoredFiles(): void
    {
        $this->write('a.php', 100);
        $fileWatcher = new FileWatcher($this->watcher([$this->dir], ignore: [$this->dir]));
        $previous = $fileWatcher->snapshot();

        $this->write('a.php', 200);

        $plan = $fileWatcher->plan($previous);

        self::assertSame([], $plan['reload']);
        self::assertSame([], $plan['restart']);
    }

    #[Test]
    public function depthOneRecursesExactlyOneLevel(): void
    {
        $this->write('a.php', 100);
        $this->write('one/b.php', 100);
        $this->write('one/two/c.php', 100);

        $snapshot = (new FileWatcher($this->watcher([$this->dir], depth: 1)))->snapshot();

        self::assertArrayHasKey($this->dir . '/a.php', $snapshot);
        self::assertArrayHasKey($this->dir . '/one/b.php', $snapshot);
        self::assertArrayNotHasKey($this->dir . '/one/two/c.php', $snapshot);
    }

    /**
     * @param list<string> $paths
     * @param list<string> $restart
     * @param list<string> $excludes
     * @param list<string> $ignore
     */
    private function watcher(array $paths, int $depth = -1, array $restart = [], array $excludes = [], array $ignore = []): WatcherInterface
    {
        return new class ($paths, $depth, $restart, $excludes, $ignore) implements WatcherInterface {
            /**
             * @param list<string> $paths
             * @param list<string> $restart
             * @param list<string> $excludes
             * @param list<string> $ignore
             */
            public function __construct(
                private readonly array $paths,
                private readonly int $depth,
                private readonly array $restart,
                private readonly array $excludes,
                private readonly array $ignore,
            ) {}

            public function paths(): array
            {
                return $this->paths;
            }

            public function extensions(): array
            {
                return ['php'];
            }

            public function excludes(): array
            {
                return $this->excludes;
            }

            public function depth(): int
            {
                return $this->depth;
            }

            public function classify(string $path): WatchAction
            {
                foreach ($this->ignore as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        return WatchAction::Ignore;
                    }
                }

                foreach ($this->restart as $prefix) {
                    if (str_starts_with($path, $prefix)) {
                        return WatchAction::Restart;
                    }
                }

                return WatchAction::Reload;
            }

            public function interval(): float
            {
                return 1.0;
            }

            public function debounce(): float
            {
                return 0.25;
            }
        };
    }

    private function write(string $relative, int $mtime): void
    {
        $path = $this->dir . '/' . $relative;
        $parent = dirname($path);

        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }

        file_put_contents($path, '<?php');
        touch($path, $mtime);
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->rmrf($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
