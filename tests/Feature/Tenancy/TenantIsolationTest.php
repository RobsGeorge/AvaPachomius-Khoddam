<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\Course;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\File;
use Tests\Support\EventModuleTestCase;

/**
 * The tenant-isolation "sacred suite" (CLAUDE.md rules 1–3) plus the cross-cutting
 * platform invariants that guard the hard rules.
 *
 * T1 activates real isolation: a church bound via TenantContext filters reads and
 * auto-stamps writes through the BelongsToChurch global scope. In production the scope
 * is dormant until the T7 cutover (ResolveTenant binds nothing while MULTI_TENANT=false),
 * so these tests drive isolation by binding a church context directly.
 */
class TenantIsolationTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_tenant_scoped_models_are_isolated_by_church(): void
    {
        $this->assertTrue(trait_exists(\App\Tenancy\BelongsToChurch::class));

        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'stmark', 'name' => 'St Mark', 'status' => 'active']);

        // Write: each row is auto-stamped with the church active at creation time.
        TenantContext::set($churchA);
        $courseA = Course::create(['title' => 'Course A', 'description' => 'x', 'year' => 2026]);
        $this->assertEquals($churchA->church_id, $courseA->church_id);

        TenantContext::set($churchB);
        $courseB = Course::create(['title' => 'Course B', 'description' => 'x', 'year' => 2026]);
        $this->assertEquals($churchB->church_id, $courseB->church_id);

        // Read isolation: under church B, church A's data is invisible (and vice-versa).
        $this->assertNull(Course::find($courseA->course_id));
        $this->assertNotNull(Course::find($courseB->course_id));

        TenantContext::set($churchA);
        $this->assertNotNull(Course::find($courseA->course_id));
        $this->assertNull(Course::find($courseB->course_id));

        // Superadmin / cross-church code bypasses the scope explicitly.
        $this->assertSame(2, Course::withoutGlobalScope('church')
            ->whereIn('course_id', [$courseA->course_id, $courseB->course_id])->count());

        // No church bound → scope no-ops (the production default while MULTI_TENANT=false).
        TenantContext::clear();
        $this->assertFalse(TenantContext::enforced());
        $this->assertSame(2, Course::whereIn('course_id', [$courseA->course_id, $courseB->course_id])->count());
    }

    public function test_church_management_models_are_isolated_by_church(): void
    {
        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'stmina-t5', 'name' => 'St Mina', 'status' => 'active']);

        $userA = $this->createUser(['email' => 'priest-a@example.com']);
        $userB = $this->createUser(['email' => 'priest-b@example.com']);

        TenantContext::set($churchA);
        $priestA = \App\Models\Priest::create([
            'user_id' => $userA->user_id,
            'title' => 'Abouna A',
            'status' => \App\Models\Priest::STATUS_ACTIVE,
        ]);
        $this->assertEquals($churchA->church_id, $priestA->church_id);

        TenantContext::set($churchB);
        $priestB = \App\Models\Priest::create([
            'user_id' => $userB->user_id,
            'title' => 'Abouna B',
            'status' => \App\Models\Priest::STATUS_ACTIVE,
        ]);
        $this->assertEquals($churchB->church_id, $priestB->church_id);

        $this->assertNull(\App\Models\Priest::find($priestA->priest_id));
        $this->assertNotNull(\App\Models\Priest::find($priestB->priest_id));

        TenantContext::set($churchA);
        $this->assertNotNull(\App\Models\Priest::find($priestA->priest_id));
        $this->assertNull(\App\Models\Priest::find($priestB->priest_id));

        TenantContext::clear();
        $this->assertSame(2, \App\Models\Priest::whereIn('priest_id', [$priestA->priest_id, $priestB->priest_id])->count());
    }

    public function test_finance_models_are_isolated_by_church(): void
    {
        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'stmark-t6', 'name' => 'St Mark T6', 'status' => 'active']);

        TenantContext::set($churchA);
        $runA = \App\Models\PayrollRun::create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => \App\Models\PayrollRun::STATUS_DRAFT,
            'currency' => 'EGP',
        ]);
        $this->assertEquals($churchA->church_id, $runA->church_id);

        TenantContext::set($churchB);
        $runB = \App\Models\PayrollRun::create([
            'period_start' => '2026-07-01',
            'period_end' => '2026-07-31',
            'status' => \App\Models\PayrollRun::STATUS_DRAFT,
            'currency' => 'EGP',
        ]);
        $this->assertEquals($churchB->church_id, $runB->church_id);
        $this->assertNull(\App\Models\PayrollRun::find($runA->payroll_run_id));

        TenantContext::set($churchA);
        $this->assertNull(\App\Models\PayrollRun::find($runB->payroll_run_id));

        TenantContext::clear();
        $this->assertSame(2, \App\Models\PayrollRun::whereIn('payroll_run_id', [$runA->payroll_run_id, $runB->payroll_run_id])->count());
    }

    public function test_a_foreign_church_id_cannot_be_mass_assigned(): void
    {
        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'stgeorge', 'name' => 'St George', 'status' => 'active']);

        // church_id is not mass-assignable; passing another church's id is ignored and the
        // row is stamped to the active church, so a write can never land in another tenant.
        TenantContext::set($churchB);
        $course = Course::create(['title' => 'Sneaky', 'description' => 'x', 'year' => 2026, 'church_id' => $churchA->church_id]);
        $this->assertEquals($churchB->church_id, $course->church_id);

        // Consequently it is hidden from church A and visible only in church B.
        TenantContext::set($churchA);
        $this->assertNull(Course::find($course->course_id));
        TenantContext::set($churchB);
        $this->assertNotNull(Course::find($course->course_id));
    }

    /**
     * Rule 4: authorization must go through policies + permission keys, never
     * hardcoded role-name string comparisons in controllers.
     */
    public function test_controllers_do_not_hardcode_role_name_checks(): void
    {
        $offenders = [];
        $pattern = '/role_name\s*(===|==|!==|!=)\s*[\'"]/';

        foreach (File::allFiles(app_path('Http/Controllers')) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            if (preg_match($pattern, File::get($file->getPathname()))) {
                $offenders[] = $file->getRelativePathname();
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Controllers must authorize via permissions, not hardcoded role names:\n"
                .implode("\n", $offenders)
        );
    }

    /**
     * Rule 6: every language file must exist in both locales so no string ships
     * untranslated. (File-level parity; key-level parity is enforced per-module.)
     */
    public function test_language_files_have_locale_parity(): void
    {
        $en = collect(File::files(lang_path('en')))->map->getFilename()->sort()->values();
        $ar = collect(File::files(lang_path('ar')))->map->getFilename()->sort()->values();

        $missingInAr = $en->diff($ar)->values()->all();
        $missingInEn = $ar->diff($en)->values()->all();

        $this->assertSame([], $missingInAr, 'English language files missing an Arabic counterpart: '.implode(', ', $missingInAr));
        $this->assertSame([], $missingInEn, 'Arabic language files missing an English counterpart: '.implode(', ', $missingInEn));
    }
}
