<?php

namespace Tests\Feature;

use App\Models\Translation;
use App\Services\TranslationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranslationRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_overrides_resolve_for_english_nav_keys(): void
    {
        Translation::create([
            'group' => 'nav',
            'key' => 'home',
            'locale' => 'en',
            'value' => 'Home from DB',
        ]);

        Translation::create([
            'group' => 'nav',
            'key' => 'service',
            'locale' => 'en',
            'value' => 'Service from DB',
        ]);

        $this->mergeEnglishNav();

        $this->assertSame('Home from DB', __('nav.home'));
        $this->assertSame('Service from DB', __('nav.service'));
        $this->assertSame('Academic', __('nav.academic'));
    }

    public function test_file_defaults_still_resolve_when_other_nav_keys_have_db_overrides(): void
    {
        Translation::create([
            'group' => 'nav',
            'key' => 'home',
            'locale' => 'en',
            'value' => 'Home from DB',
        ]);

        $this->mergeEnglishNav();

        $this->assertSame('Home from DB', __('nav.home'));
        $this->assertSame('Academic', __('nav.academic'));
        $this->assertSame('Service', __('nav.service'));
        $this->assertSame('System settings', __('nav.system_settings'));
        $this->assertSame('Super admin', __('nav.superadmin'));
    }

    public function test_self_key_database_overrides_are_ignored_so_file_defaults_apply(): void
    {
        Translation::create([
            'group' => 'nav',
            'key' => 'home',
            'locale' => 'en',
            'value' => 'nav.home',
        ]);

        $this->mergeEnglishNav();

        $this->assertSame('Home', __('nav.home'));
    }

    private function mergeEnglishNav(): void
    {
        app(TranslationRepository::class)->flushCache('en');
        app()->setLocale('en');
        app(TranslationRepository::class)->mergeDatabaseLines('en');
    }
}
