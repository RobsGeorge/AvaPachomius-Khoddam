<?php

namespace Tests\Unit\Support;

use App\Exceptions\StoragePermissionException;
use App\Support\StoragePermissionError;
use ErrorException;
use RuntimeException;
use Tests\TestCase;

class StoragePermissionErrorTest extends TestCase
{
    public function test_matches_chmod_operation_not_permitted(): void
    {
        $this->assertTrue(StoragePermissionError::matches(
            new ErrorException('chmod(): Operation not permitted')
        ));
    }

    public function test_matches_file_put_contents_permission_denied(): void
    {
        $this->assertTrue(StoragePermissionError::matches(
            new ErrorException('file_put_contents(/var/www/avapakhomios/storage/framework/cache/data/ab/cd/hash): Failed to open stream: Permission denied')
        ));
    }

    public function test_matches_storage_permission_exception(): void
    {
        $this->assertTrue(StoragePermissionError::matches(
            StoragePermissionException::fromThrowable(new ErrorException('chmod(): Operation not permitted'))
        ));
    }

    public function test_ignores_unrelated_errors(): void
    {
        $this->assertFalse(StoragePermissionError::matches(
            new ErrorException('Undefined variable: foo')
        ));
        $this->assertFalse(StoragePermissionError::matches(
            new RuntimeException('disk exploded')
        ));
        $this->assertFalse(StoragePermissionError::matches(
            new ErrorException('Permission denied')
        ));
    }
}
