<?php

declare(strict_types=1);

namespace PHPdot\Server\Swoole\Watch;

use PHPdot\Server\Swoole\Contract\WatcherInterface;
use PHPdot\Server\Swoole\Enum\WatchAction;
use PHPdot\Server\Swoole\SwooleServer;
use Swoole\Coroutine;
use Swoole\Process;

/**
 * FileWatcher.
 *
 * The development hot-reload engine. Runs as a Swoole user process (attach it
 * with SwooleServer::addProcess), polling the files described by a
 * WatcherInterface. On change it reloads the workers for app code — SIGUSR1 to
 * the master, which only reloads code loaded after the fork — and prints a
 * notice that a full restart is required for code loaded before the fork
 * (config, bootstrap). All policy lives in WatcherInterface; this is mechanism.
 *
 * @author Omar Hamdan <omar@phpdot.com>
 * @license MIT
 */
final class FileWatcher
{
    private bool $running = true;

    public function __construct(
        private readonly WatcherInterface $watcher,
    ) {}

    /**
     * Run the poll loop. Blocks until stop() is called or — the normal case —
     * the Swoole master terminates this user process on server shutdown.
     */
    public function run(SwooleServer $server): void
    {
        // Never act on Ctrl+C from this user process — only the master shuts the
        // server down (and it tears this process down as part of that). Ignoring
        // SIGINT keeps a terminal interrupt from killing the watcher out of order.
        Process::signal(SIGINT, static function (): void {});

        $masterPid = $server->getMasterPid();
        $previous = $this->snapshot();

        while ($this->running) {
            Coroutine::sleep($this->watcher->interval());

            // Exit if the server is gone, so this user process never orphans.
            if ($masterPid > 0 && Process::kill($masterPid, 0) === false) {
                break;
            }

            $plan = $this->plan($previous);

            if ($plan['reload'] === [] && $plan['restart'] === []) {
                $previous = $plan['snapshot'];

                continue;
            }

            // Let a burst settle, then re-plan against the same baseline so a
            // multi-file save collapses into a single action.
            Coroutine::sleep($this->watcher->debounce());
            $plan = $this->plan($previous);

            $this->act($server, $plan['reload'], $plan['restart']);
            $previous = $plan['snapshot'];
        }
    }

    /**
     * Stop the poll loop after the current iteration.
     */
    public function stop(): void
    {
        $this->running = false;
    }

    /**
     * Current snapshot: absolute file path => change signature (mtime + size, so
     * a same-second edit that also changes the size is still detected).
     *
     * @return array<string, string>
     */
    public function snapshot(): array
    {
        // The poll loop is long-lived; without this, filemtime()/filesize()
        // return cached values and changes are never detected.
        clearstatcache();

        $files = [];

        foreach ($this->watcher->paths() as $root) {
            $this->scan($root, 0, $files);
        }

        return $files;
    }

    /**
     * Diff a previous snapshot against the current one and classify each change.
     *
     * @param array<string, string> $previous
     * @return array{reload: list<string>, restart: list<string>, snapshot: array<string, string>}
     */
    public function plan(array $previous): array
    {
        $snapshot = $this->snapshot();
        $reload = [];
        $restart = [];

        foreach ($this->changed($previous, $snapshot) as $path) {
            match ($this->watcher->classify($path)) {
                WatchAction::Reload => $reload[] = $path,
                WatchAction::Restart => $restart[] = $path,
                WatchAction::Ignore => null,
            };
        }

        return ['reload' => $reload, 'restart' => $restart, 'snapshot' => $snapshot];
    }

    /**
     * @param array<string, string> $previous
     * @param array<string, string> $current
     * @return list<string>
     */
    private function changed(array $previous, array $current): array
    {
        $changed = [];

        foreach ($current as $path => $signature) {
            if (($previous[$path] ?? null) !== $signature) {
                $changed[] = $path;
            }
        }

        foreach ($previous as $path => $signature) {
            if (!isset($current[$path])) {
                $changed[] = $path;
            }
        }

        return $changed;
    }

    /**
     * @param array<string, string> $files
     */
    private function scan(string $dir, int $level, array &$files): void
    {
        $handle = @opendir($dir);

        if ($handle === false) {
            return;
        }

        $extensions = $this->watcher->extensions();
        $excludes = $this->watcher->excludes();
        $depth = $this->watcher->depth();

        while (($entry = readdir($handle)) !== false) {
            if ($entry === '.' || $entry === '..' || $this->excluded($entry, $excludes)) {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $entry;

            if (is_dir($path)) {
                // Skip symlinked directories so a cycle can't loop the watcher.
                if (!is_link($path) && ($depth === -1 || $level < $depth)) {
                    $this->scan($path, $level + 1, $files);
                }

                continue;
            }

            if (!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), $extensions, true)) {
                continue;
            }

            $mtime = @filemtime($path);
            $size = @filesize($path);

            if ($mtime !== false && $size !== false) {
                $files[$path] = $mtime . ':' . $size;
            }
        }

        closedir($handle);
    }

    /**
     * @param list<string> $excludes
     */
    private function excluded(string $name, array $excludes): bool
    {
        foreach ($excludes as $pattern) {
            if (fnmatch($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $reload
     * @param list<string> $restart
     */
    private function act(SwooleServer $server, array $reload, array $restart): void
    {
        if ($reload !== []) {
            $this->notice('reloaded', $reload);
            Process::kill($server->getMasterPid(), SIGUSR1);
        }

        if ($restart !== []) {
            $this->notice('restart required', $restart);
        }
    }

    /**
     * @param list<string> $paths
     */
    private function notice(string $label, array $paths): void
    {
        $cwd = getcwd();
        $relative = array_map(
            static function (string $path) use ($cwd): string {
                if ($cwd !== false && str_starts_with($path, $cwd)) {
                    return ltrim(substr($path, strlen($cwd)), DIRECTORY_SEPARATOR);
                }

                return $path;
            },
            $paths,
        );

        echo sprintf("[watch] %s: %s\n", $label, implode(', ', $relative));
    }
}
