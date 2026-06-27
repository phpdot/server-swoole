<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Tests\Unit\Watch;

use PHPdot\Server\Swoole\Enum\WatchAction;
use PHPdot\Server\Swoole\Watch\Watcher;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class WatcherTest extends TestCase
{
    #[Test]
    public function exposesConfiguredValues(): void
    {
        $watcher = new Watcher(
            paths: ['/app/src'],
            extensions: ['php', 'twig'],
            excludes: ['vendor', '*.log'],
            restart: ['/app/config'],
            depth: 2,
            interval: 0.5,
            debounce: 0.1,
        );

        self::assertSame(['/app/src'], $watcher->paths());
        self::assertSame(['php', 'twig'], $watcher->extensions());
        self::assertSame(['vendor', '*.log'], $watcher->excludes());
        self::assertSame(2, $watcher->depth());
        self::assertSame(0.5, $watcher->interval());
        self::assertSame(0.1, $watcher->debounce());
    }

    #[Test]
    public function appliesGenericDefaultsWhenUnset(): void
    {
        $watcher = new Watcher();

        $cwd = getcwd();
        self::assertSame([$cwd !== false ? $cwd : '.'], $watcher->paths());
        self::assertSame(['php'], $watcher->extensions());
        self::assertSame(['vendor', '.git'], $watcher->excludes());
        self::assertSame(-1, $watcher->depth());
    }

    #[Test]
    public function classifiesFilesUnderRestartPrefixAsRestart(): void
    {
        $watcher = new Watcher(paths: ['/app'], restart: ['/app/config']);

        self::assertSame(WatchAction::Restart, $watcher->classify('/app/config/server.php'));
    }

    #[Test]
    public function classifiesOtherFilesAsReload(): void
    {
        $watcher = new Watcher(paths: ['/app'], restart: ['/app/config']);

        self::assertSame(WatchAction::Reload, $watcher->classify('/app/Http/OkHandler.php'));
    }

    #[Test]
    public function doesNotFalseMatchSimilarlyNamedSiblings(): void
    {
        $watcher = new Watcher(paths: ['/app'], restart: ['/app/config']);

        // '/app/config_helper.php' begins with '/app/config' but is not under it.
        self::assertSame(WatchAction::Reload, $watcher->classify('/app/config_helper.php'));
    }

    #[Test]
    public function matchesRestartByRelativeSegment(): void
    {
        $watcher = new Watcher(restart: ['config']);

        self::assertSame(WatchAction::Restart, $watcher->classify('/app/protected/config/server.php'));
        self::assertSame(WatchAction::Reload, $watcher->classify('/app/protected/config_helper.php'));
    }
}
