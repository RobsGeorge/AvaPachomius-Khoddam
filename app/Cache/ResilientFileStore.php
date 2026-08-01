<?php

namespace App\Cache;

use App\Exceptions\StoragePermissionException;
use App\Filesystem\SoftChmodFilesystem;
use App\Support\StoragePermissionError;
use ErrorException;
use Illuminate\Cache\FileStore;
use Throwable;

/**
 * File cache store that recovers from missing hash directories and common
 * permission races between deploy (deploy user) and runtime (www-data / PHP-FPM).
 *
 * Classic prod failure: deploy/cron creates hash dirs under umask 0022 → mode 2755
 * owned by deploy:www-data. www-data can often rewrite via group/parent, but
 * Laravel's chmod() throws "Operation not permitted" and used to 500 the request
 * (login, impersonation, CoursePermissionResolver cache writes, etc.).
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
     * Soft-fail when www-data cannot chmod a deploy-owned node.
     *
     * @param  string  $path
     */
    protected function ensurePermissionsAreCorrect($path): void
    {
        try {
            parent::ensurePermissionsAreCorrect($path);
        } catch (ErrorException $e) {
            if ($this->files instanceof SoftChmodFilesystem
                && $this->files->isIgnorableChmodFailure($e)) {
                return;
            }

            if ($this->isChmodOwnershipError($e)) {
                return;
            }

            throw $e;
        }
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

            $this->ensureWritableCachePath(dirname($this->path($key)), recreateUnwritable: true);

            return $callback();
        }
    }

    protected function ensureWritableCachePath(string $directory, bool $recreateUnwritable = false): void
    {
        if (! $this->files->isDirectory($this->directory)) {
            $this->createDirectoryTree($this->directory);
        } else {
            $this->ensureDirectoryWritable($this->directory, allowRecreate: false);
        }

        if (! $this->files->isDirectory($directory)) {
            $this->createDirectoryTree($directory);
        } else {
            $this->ensureDirectoryWritable($directory, allowRecreate: $recreateUnwritable);
        }
    }

    protected function createDirectoryTree(string $path): void
    {
        if ($this->files->isDirectory($path)) {
            $this->ensureDirectoryWritable($path, allowRecreate: false);

            return;
        }

        $this->files->makeDirectory($path, 02775, true, true);

        if ($this->files->isDirectory($path)) {
            $this->ensureDirectoryWritable($path, allowRecreate: false);

            return;
        }

        // makeDirectory(..., $force = true) suppresses mkdir errors — retry without @.
        mkdir($path, 02775, true);
        $this->ensureDirectoryWritable($path, allowRecreate: false);
    }

    protected function ensureDirectoryWritable(string $path, bool $allowRecreate = false): void
    {
        if (! $this->files->isDirectory($path)) {
            return;
        }

        if (is_writable($path)) {
            return;
        }

        try {
            $this->files->chmod($path, 02775);
        } catch (ErrorException $e) {
            if (! $this->isChmodOwnershipError($e)) {
                throw $e;
            }
        }

        clearstatcache(true, $path);

        if (is_writable($path)) {
            return;
        }

        if (! $allowRecreate || ! $this->canSafelyRecreateCacheDirectory($path)) {
            return;
        }

        // Cannot delete contents of a non-writable dir; rename only needs write on the parent.
        $trashed = $path.'.unwritable.'.str_replace('.', '', uniqid('', true));
        if (! @rename($path, $trashed)) {
            return;
        }

        $this->createDirectoryTree($path);

        // Best-effort cleanup; deploy perms helper also sweeps these leftovers.
        try {
            if ($this->files->isDirectory($trashed)) {
                @chmod($trashed, 02775);
                $this->files->deleteDirectory($trashed);
            }
        } catch (Throwable) {
            // Left for avapakhomios-deploy-perms / next deploy.
        }
    }

    /**
     * Only recreate hash dirs under the configured cache root — never the root itself.
     */
    protected function canSafelyRecreateCacheDirectory(string $path): bool
    {
        $root = realpath($this->directory) ?: $this->directory;
        $real = realpath($path) ?: $path;

        $root = rtrim(str_replace('\\', '/', $root), '/');
        $real = rtrim(str_replace('\\', '/', $real), '/');

        if ($real === $root || ! str_starts_with($real, $root.'/')) {
            return false;
        }

        $parent = dirname($real);

        return is_dir($parent) && is_writable($parent);
    }

    protected function isRecoverableCacheWriteError(Throwable $e): bool
    {
        if (! $e instanceof ErrorException) {
            return false;
        }

        if ($this->isChmodOwnershipError($e)) {
            return true;
        }

        $message = $e->getMessage();

        if (str_contains($message, 'Permission denied')) {
            return str_contains($message, 'Failed to open stream')
                || str_contains($message, 'failed to open stream')
                || str_contains($message, 'file_put_contents')
                || str_contains($message, 'mkdir():');
        }

        if (! str_contains($message, 'No such file or directory')) {
            return false;
        }

        return str_contains($message, 'Failed to open stream')
            || str_contains($message, 'failed to open stream')
            || str_contains($message, 'mkdir():');
    }

    protected function isChmodOwnershipError(ErrorException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'chmod():')
            && (str_contains($message, 'Operation not permitted')
                || str_contains($message, 'Permission denied'));
    }
}
