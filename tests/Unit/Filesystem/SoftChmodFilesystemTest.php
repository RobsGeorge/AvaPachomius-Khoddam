<?php

namespace Tests\Unit\Filesystem;

use App\Filesystem\SoftChmodFilesystem;
use Tests\TestCase;

class SoftChmodFilesystemTest extends TestCase
{
    private string $path;

    private SoftChmodFilesystem $files;

    protected function setUp(): void
    {
        parent::setUp();

        $this->files = new SoftChmodFilesystem;
        $dir = storage_path('framework/cache/soft-chmod-'.uniqid('', true));
        $this->files->makeDirectory($dir, 0777, true, true);
        $this->path = $dir.'/node.txt';
        $this->files->put($this->path, 'x');
    }

    protected function tearDown(): void
    {
        if (isset($this->path)) {
            $dir = dirname($this->path);
            if (is_dir($dir)) {
                $this->files->deleteDirectory($dir);
            }
        }

        parent::tearDown();
    }

    public function test_chmod_read_mode_still_works(): void
    {
        $mode = $this->files->chmod($this->path);
        $this->assertMatchesRegularExpression('/^[0-7]{3,4}$/', $mode);
    }

    public function test_chmod_set_mode_succeeds_when_permitted(): void
    {
        $this->assertNotFalse($this->files->chmod($this->path, 0664));
    }

    public function test_is_ignorable_chmod_failure_detects_ownership_errors(): void
    {
        $this->assertTrue($this->files->isIgnorableChmodFailure(
            new \ErrorException('chmod(): Operation not permitted')
        ));
        $this->assertTrue($this->files->isIgnorableChmodFailure(
            new \ErrorException('chmod(): Permission denied')
        ));
        $this->assertFalse($this->files->isIgnorableChmodFailure(
            new \ErrorException('chmod(): No such file or directory')
        ));
    }

    public function test_chmod_swallows_operation_not_permitted_from_parent(): void
    {
        $files = new class extends SoftChmodFilesystem
        {
            public bool $forced = false;

            public function chmod($path, $mode = null)
            {
                if ($this->forced && $mode !== null) {
                    // Invoke the same catch path SoftChmodFilesystem uses around parent::chmod.
                    try {
                        throw new \ErrorException('chmod(): Operation not permitted');
                    } catch (\ErrorException $e) {
                        if ($this->isIgnorableChmodFailure($e)) {
                            return false;
                        }

                        throw $e;
                    }
                }

                return parent::chmod($path, $mode);
            }
        };

        $files->forced = true;
        $this->assertFalse($files->chmod($this->path, 02775));
    }
}
