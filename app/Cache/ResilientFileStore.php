<?php

namespace App\Cache;

use ErrorException;
use Illuminate\Cache\FileStore;
use Throwable;

/**
 * File cache store that recovers from missing hash directories and common
 * permission races between deploy (deploy user) and runtime (www-data / PHP-FPM).
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
            if (! $this->isRecoverableCacheWriteError($e)) {
                throw $e;
            }

            $this->ensureWritableCachePath(dirname($this->path($key)));

            return $callback();
        }
    }

    protected function ensureWritableCachePath(string $directory): void
    {
        if (! $this->files->isDirectory($this->directory)) {
            $this->createDirectoryTree($this->directory);
        } else {
            $this->ensureDirectoryWritable($this->directory);
        }

        if (! $this->files->isDirectory($directory)) {
            $this->createDirectoryTree($directory);
        } else {
            $this->ensureDirectoryWritable($directory);
        }
    }

    protected function createDirectoryTree(string $path): void
    {
        if ($this->files->isDirectory($path)) {
            $this->ensureDirectoryWritable($path);

            return;
        }

        $this->files->makeDirectory($path, 02775, true, true);

        if ($this->files->isDirectory($path)) {
            $this->ensureDirectoryWritable($path);

            return;
        }

        // makeDirectory(..., $force = true) suppresses mkdir errors — retry without @.
        mkdir($path, 02775, true);
        $this->ensureDirectoryWritable($path);
    }

    protected function ensureDirectoryWritable(string $path): void
    {
        if (! $this->files->isDirectory($path)) {
            return;
        }

        if (is_writable($path)) {
            return;
        }

        $this->files->chmod($path, 02775);
    }

    protected function isRecoverableCacheWriteError(Throwable $e): bool
    {
        if (! $e instanceof ErrorException) {
            return false;
        }

        $message = $e->getMessage();

        if (str_contains($message, 'Permission denied')) {
            return str_contains($message, 'Failed to open stream')
                || str_contains($message, 'failed to open stream')
                || str_contains($message, 'file_put_contents');
        }

        if (! str_contains($message, 'No such file or directory')) {
            return false;
        }

        return str_contains($message, 'Failed to open stream')
            || str_contains($message, 'failed to open stream')
            || str_contains($message, 'mkdir():');
    }
}
