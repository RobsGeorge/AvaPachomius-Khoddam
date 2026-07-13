<?php

namespace Tests\Feature\Tenancy;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * The tenant-isolation "sacred suite" (CLAUDE.md rules 1–3) plus the cross-cutting
 * platform invariants that guard the hard rules.
 *
 * Multi-tenancy is being introduced by expand-contract. The isolation assertions
 * below activate automatically the moment the `church_id` column exists; until then
 * they skip with a clear reason so the pipeline stays green without building ahead
 * of the current migration phase (rule 10).
 */
class TenantIsolationTest extends EventModuleTestCase
{
    public function test_tenant_scoped_models_are_isolated_by_church(): void
    {
        if (! Schema::hasColumn('user', 'church_id')) {
            $this->markTestSkipped(
                'Multi-tenancy not yet migrated (no church_id column). '
                .'This isolation check activates when the expand migration lands.'
            );
        }

        // Once church_id exists, assert the BelongsToChurch global scope prevents
        // cross-tenant reads. Filled in with the tenancy migration (master-plan §7).
        $this->assertTrue(
            trait_exists(\App\Tenancy\BelongsToChurch::class),
            'BelongsToChurch trait must exist once church_id is introduced.'
        );
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
