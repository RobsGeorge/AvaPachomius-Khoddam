<?php

namespace App\Filesystem;

use ErrorException;
use Illuminate\Filesystem\Filesystem;

/**
 * Filesystem that never turns a failed chmod into a 500.
 *
 * Production runs PHP-FPM as www-data while deploy/cron often create
 * storage files as the deploy user (group www-data). Group write is enough
 * for cache/session I/O; chmod requires ownership and must not abort requests.
 */
class SoftChmodFilesystem extends Filesystem
{
    /**
     * @param  string  $path
     * @param  int|null  $mode
     * @return mixed
     */
    public function chmod($path, $mode = null)
    {
        if ($mode === null) {
            return parent::chmod($path, null);
        }

        try {
            return parent::chmod($path, $mode);
        } catch (ErrorException $e) {
            if ($this->isIgnorableChmodFailure($e)) {
                return false;
            }

            throw $e;
        }
    }

    public function isIgnorableChmodFailure(ErrorException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'Operation not permitted')
            || str_contains($message, 'Permission denied');
    }
}
