<?php

namespace App\Support;

use App\Exceptions\StoragePermissionException;
use ErrorException;
use Throwable;

/**
 * Detects the recurring deploy-vs-www-data storage permission failures
 * (chmod Operation not permitted, file_put_contents Permission denied, …).
 */
final class StoragePermissionError
{
    public static function matches(Throwable $e): bool
    {
        if ($e instanceof StoragePermissionException) {
            return true;
        }

        if (! $e instanceof ErrorException) {
            return false;
        }

        $message = $e->getMessage();

        if (str_contains($message, 'chmod():')
            && (str_contains($message, 'Operation not permitted')
                || str_contains($message, 'Permission denied'))) {
            return true;
        }

        if (! str_contains($message, 'Permission denied')) {
            return false;
        }

        return str_contains($message, 'file_put_contents')
            || str_contains($message, 'Failed to open stream')
            || str_contains($message, 'failed to open stream')
            || str_contains($message, 'mkdir():')
            || str_contains($message, 'rename(')
            || str_contains($message, 'unlink(')
            || str_contains($message, 'rmdir(');
    }
}
