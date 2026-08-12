<?php

namespace Tests\Unit;

use App\Models\Church;
use App\Models\Organization;
use App\Services\ChurchSlugSuggester;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChurchSlugSuggesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_suggests_base_slug_from_short_name(): void
    {
        $suggestions = app(ChurchSlugSuggester::class)->suggest([
            'short_name' => 'St Mark',
            'name' => 'Saint Mark Coptic Orthodox Church',
            'place_country_code' => 'EG',
            'place_governorate' => 'Alexandria',
        ]);

        $this->assertNotEmpty($suggestions);
        $this->assertSame('st-mark', $suggestions[0]);
        $this->assertContains('st-mark-eg', $suggestions);
    }

    public function test_skips_taken_slug_and_appends_place(): void
    {
        Church::create([
            'slug' => 'st-mary',
            'name' => 'Existing',
            'status' => 'active',
            'permissions_version' => 1,
        ]);

        $suggestions = app(ChurchSlugSuggester::class)->suggest([
            'short_name' => 'St Mary',
            'place_country_code' => 'EG',
            'place_governorate' => 'Cairo',
            'place_district' => 'Maadi',
        ]);

        $this->assertNotContains('st-mary', $suggestions);
        $this->assertContains('st-mary-eg', $suggestions);
    }

    public function test_arabic_short_name_transliterates_to_non_empty_slug(): void
    {
        $slug = app(ChurchSlugSuggester::class)->toSlug('العذراء');
        $this->assertNotSame('', $slug);
        $this->assertMatchesRegularExpression('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug);
        $this->assertLessThanOrEqual(40, strlen($slug));
    }

    public function test_unavailable_when_organization_subdomain_taken(): void
    {
        if (! Schema::hasTable('organizations')) {
            $this->markTestSkipped('organizations table missing');
        }

        Organization::query()->create([
            'type' => 'church',
            'subdomain' => 'taken-slug',
            'name' => 'Org',
            'status' => 'active',
            'onboarding_state' => [],
        ]);

        $this->assertFalse(app(ChurchSlugSuggester::class)->isAvailable('taken-slug'));
    }
}
