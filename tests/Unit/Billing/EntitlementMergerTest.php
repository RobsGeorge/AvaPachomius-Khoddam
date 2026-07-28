<?php

namespace Tests\Unit\Billing;

use App\Billing\EntitlementMerger;
use Tests\TestCase;

class EntitlementMergerTest extends TestCase
{
    public function test_boolean_or_merge(): void
    {
        $merger = new EntitlementMerger();

        $this->assertTrue($merger->mergeValue('exams', false, true));
        $this->assertTrue($merger->mergeValue('exams', true, false));
        $this->assertFalse($merger->mergeValue('exams', false, false));
    }

    public function test_limit_takes_max_and_null_is_unlimited(): void
    {
        $merger = new EntitlementMerger();

        $this->assertSame(200, $merger->mergeValue('max_active_users', 50, 200));
        $this->assertNull($merger->mergeValue('storage_bytes', null, 10));
        $this->assertNull($merger->mergeValue('storage_bytes', 10, null));
    }

    public function test_mobile_app_enum_takes_richer_rank(): void
    {
        $merger = new EntitlementMerger();

        $this->assertSame('full', $merger->mergeValue('mobile_app', 'student', 'full'));
        $this->assertSame('student', $merger->mergeValue('mobile_app', 'none', 'student'));
        $this->assertSame('student', $merger->mergeValue('mobile_app', 'student', 'none'));
    }

    public function test_merge_maps_all_keys(): void
    {
        $merger = new EntitlementMerger();
        $merged = $merger->merge(
            ['exams' => false, 'max_active_users' => 50],
            ['exams' => true, 'live_quiz' => true]
        );

        $this->assertTrue($merged['exams']);
        $this->assertTrue($merged['live_quiz']);
        $this->assertSame(50, $merged['max_active_users']);
    }
}
