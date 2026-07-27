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
 * One forced recreate + retry prevents that ErrorException from reaching users.
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

            $directory = dirname($this->path($key));
            $this->files->makeDirectory($directory, 0777, true, true);

            // Also recreate the configured base path if a flush removed parents.
            if (! $this->files->isDirectory($this->directory)) {
                $this->files->makeDirectory($this->directory, 0777, true, true);
                $this->files->makeDirectory($directory, 0777, true, true);
            }

            return $callback();
        }
    }

    protected function isMissingCachePathError(Throwable $e): bool
    {
        if (! $e instanceof ErrorException) {
            return false;
        }

        $message = $e->getMessage();

        return str_contains($message, 'Failed to open stream: No such file or directory')
            || str_contains($message, 'failed to open stream: No such file or directory');
    }
}
