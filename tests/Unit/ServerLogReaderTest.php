<?php

namespace Tests\Unit;

use App\Services\ServerLogReader;
use PHPUnit\Framework\TestCase;

class ServerLogReaderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/server-log-reader-'.uniqid('', true);
        mkdir($this->dir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function test_parses_error_entries_newest_first_without_stack_noise(): void
    {
        $body = <<<'LOG'
[2026-08-01 10:00:00] local.INFO: Warmup complete
[2026-08-01 10:05:00] local.ERROR: Payment gateway timeout {"user_id":12,"exception":"[object] (RuntimeException(code: 0): Payment gateway timeout at /app/Foo.php:12)
[stacktrace]
#0 /var/www/app/Http/Controllers/Foo.php(12): Foo->bar()
"}
[2026-08-01 11:00:00] local.CRITICAL: Queue worker died
LOG;

        file_put_contents($this->dir.'/laravel.log', $body);

        $reader = new ServerLogReader($this->dir);
        $entries = $reader->recent(limit: 10);

        $this->assertCount(2, $entries);
        $this->assertSame('2026-08-01 11:00:00', $entries[0]['time']);
        $this->assertSame('CRITICAL', $entries[0]['level']);
        $this->assertSame('Queue worker died', $entries[0]['message']);
        $this->assertSame('2026-08-01 10:05:00', $entries[1]['time']);
        $this->assertSame('ERROR', $entries[1]['level']);
        $this->assertSame('Payment gateway timeout', $entries[1]['message']);
        $this->assertStringNotContainsString('stacktrace', $entries[1]['message']);
    }

    public function test_all_levels_includes_info(): void
    {
        file_put_contents($this->dir.'/laravel.log', "[2026-08-01 09:00:00] local.INFO: Hello\n");

        $reader = new ServerLogReader($this->dir);
        $entries = $reader->recent(limit: 5, levels: null);

        $this->assertCount(1, $entries);
        $this->assertSame('INFO', $entries[0]['level']);
        $this->assertSame('Hello', $entries[0]['message']);
    }
}
