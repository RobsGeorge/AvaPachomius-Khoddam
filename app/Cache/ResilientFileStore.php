<?php

namespace App\Cache;

use ErrorException;
use Illuminate\Cache\FileStore;
use Throwable;

/**
 * File cache store that recovers from missing hash directories.
 *
 * Laravel's FileStore checks that the two-level hash path exists, then writes.
 * Under concurrency (cache:clear / flush during traffic, or deploy optimize:clear
 * racing PHP-FPM workers) the directory can disappear between those steps.
 * makeDirectory(..., $force = true) also swallows mkdir failures, so a later
 * file_put_contents surfaces as "No such file or directory".
 *
 * Proactive directory creation plus one forced recreate + retry prevents that
 * ErrorException from reaching users.
 */
class ResilientFileStore extends FileStore
{
    public function put($key, $value, $seconds)
    {
        return $this->retryAfterEnsuringDirectory($key, function () use ($key, $value, $seconds) {
            return parent::put($key, $value, $seconds);
        });
    }

    public function add($key, $value, $seconds)
    {
        return $this->retryAfterEnsuringDirectory($key, function () use ($key, $value, $seconds) {
            return parent::add($key, $value, $seconds);
        });
    }

    /**
     * @param  string  $path
     */
    protected function ensureCacheDirectoryExists($path): void
    {
        $this->ensureWritableCachePath(dirname($path));
    }

    /**
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    protected function retryAfterEnsuringDirectory(string $key, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $e) {
            if (! $this->isMissingCachePathError($e)) {
                throw $e;
            }

            $this->ensureWritableCachePath(dirname($this->path($key)));

            return $callback();
        }
    }

    protected function ensureWritableCachePath(string $directory): void
    {
        if ($this->files->isDirectory($directory)) {
            return;
        }

        if (! $this->files->isDirectory($this->directory)) {
            $this->createDirectoryTree($this->directory);
        }

        $this->createDirectoryTree($directory);
    }

    protected function createDirectoryTree(string $path): void
    {
        if ($this->files->isDirectory($path)) {
            return;
        }

        $this->files->makeDirectory($path, 0775, true, true);

        if ($this->files->isDirectory($path)) {
            return;
        }

        // makeDirectory(..., $force = true) suppresses mkdir errors — retry without @.
        mkdir($path, 0775, true);
    }

    protected function isMissingCachePathError(Throwable $e): bool
    {
        $message = $e->getMessage();

        if (! str_contains($message, 'No such file or directory')) {
            return false;
        }

        return $e instanceof ErrorException
            || str_contains($message, 'Failed to open stream')
            || str_contains($message, 'failed to open stream')
            || str_contains($message, 'mkdir():');
    }
}
