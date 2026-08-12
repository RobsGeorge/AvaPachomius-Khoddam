<?php

namespace Tests\Feature\PublicSite;

use App\Models\Church;
use App\Models\ChurchCapability;
use App\Models\ChurchMedia;
use App\Models\ChurchSite;
use App\Models\ChurchSiteSection;
use App\Models\ChurchUser;
use App\Models\UserChurchRole;
use App\Services\RoleTemplateService;
use App\Support\PublicSite\SectionTypes;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\Support\EventModuleTestCase;

/**
 * T10c — curated homepage CMS (draft/publish, media, tenant isolation).
 */
class ChurchHomepageCmsTest extends EventModuleTestCase
{
    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('permissions:sync');
        Storage::fake('public');
    }

    public function test_permissions_sync_has_manage_and_publish(): void
    {
        foreach (['public_site.manage', 'public_site.publish'] as $key) {
            $this->assertDatabaseHas('permissions', ['key' => $key]);
        }
    }

    public function test_church_admin_can_open_editor_servant_forbidden(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');
        [, $servant] = $this->churchWithRole('servant');

        $this->actingAs($admin)
            ->get(route('site.homepage.edit'))
            ->assertOk()
            ->assertSee(__('public_site.homepage_edit_title'));

        $this->actingAs($servant)
            ->get(route('site.homepage.edit'))
            ->assertForbidden();
    }

    public function test_admin_adds_hero_draft_guest_home_redirects_to_login(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->post(route('site.homepage.sections.store'), ['type' => SectionTypes::HERO])
            ->assertRedirect(route('site.homepage.edit'));

        $section = ChurchSiteSection::query()->where('type', SectionTypes::HERO)->first();
        $this->assertNotNull($section);

        $this->actingAs($admin)
            ->put(route('site.homepage.sections.update', $section), [
                'headline_ar' => 'مرحباً',
                'headline_en' => 'Welcome draft',
                'enabled_draft' => '1',
            ])
            ->assertRedirect(route('site.homepage.edit'));

        auth()->logout();
        $this->get(route('home'))
            ->assertRedirect(route('login'));
    }

    public function test_publish_audits_and_guest_sees_hero_headline(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)
            ->post(route('site.homepage.sections.store'), ['type' => SectionTypes::HERO]);

        $section = ChurchSiteSection::query()->where('type', SectionTypes::HERO)->first();
        $this->assertNotNull($section);

        $this->actingAs($admin)
            ->put(route('site.homepage.sections.update', $section), [
                'headline_ar' => 'مرحباً',
                'headline_en' => 'Welcome Home',
                'enabled_draft' => '1',
            ]);

        $this->actingAs($admin)
            ->post(route('site.homepage.publish'))
            ->assertRedirect(route('site.homepage.edit'));

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'public_site.published',
            'user_id' => $admin->user_id,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('مرحباً', false);
    }

    public function test_unpublish_redirects_guest_to_login_and_audits(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');

        $this->actingAs($admin)->post(route('site.homepage.sections.store'), ['type' => SectionTypes::HERO]);
        $section = ChurchSiteSection::query()->where('type', SectionTypes::HERO)->first();
        $this->actingAs($admin)->put(route('site.homepage.sections.update', $section), [
            'headline_en' => 'Live headline',
            'enabled_draft' => '1',
        ]);
        $this->actingAs($admin)->post(route('site.homepage.publish'));

        $this->actingAs($admin)
            ->post(route('site.homepage.unpublish'))
            ->assertRedirect(route('site.homepage.edit'));

        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'public_site.unpublished',
            'user_id' => $admin->user_id,
        ]);

        auth()->logout();
        $this->get(route('home'))
            ->assertRedirect(route('login'));
    }

    public function test_tenant_isolation_for_sections_and_http(): void
    {
        config(['tenancy.enabled' => true]);

        $churchA = Church::main();
        $churchB = Church::create(['slug' => 'st-george-t10c', 'name' => 'St George', 'status' => 'active']);
        $this->enablePublicSiteCapability($churchB);

        TenantContext::set($churchA);
        $siteA = ChurchSite::create(['church_id' => $churchA->church_id, 'theme_draft' => []]);
        $sectionA = ChurchSiteSection::create([
            'church_id' => $churchA->church_id,
            'church_site_id' => $siteA->church_site_id,
            'type' => SectionTypes::HERO,
            'sort_order' => 1,
            'enabled_draft' => true,
            'content_draft' => SectionTypes::defaults(SectionTypes::HERO),
        ]);

        TenantContext::set($churchB);
        $this->assertNull(ChurchSiteSection::find($sectionA->church_site_section_id));

        [, $adminB] = $this->churchWithRoleOnChurch($churchB, 'church-admin');
        TenantContext::set($churchB);

        $this->actingAs($adminB)
            ->put(route('site.homepage.sections.update', $sectionA), [
                'headline_en' => 'Hacked',
                'enabled_draft' => '1',
            ])
            ->assertNotFound();
    }

    public function test_media_upload_and_block_delete_when_used_in_published_gallery(): void
    {
        [, $admin] = $this->churchWithRole('church-admin');
        $file = UploadedFile::fake()->image('gallery.jpg', 400, 300);

        $this->actingAs($admin)
            ->post(route('site.media.store'), [
                'file' => $file,
                'alt_en' => 'Alt text',
            ])
            ->assertRedirect(route('site.homepage.edit'));

        $media = ChurchMedia::query()->first();
        $this->assertNotNull($media);
        Storage::disk('public')->assertExists($media->path);

        $this->actingAs($admin)
            ->post(route('site.homepage.sections.store'), ['type' => SectionTypes::GALLERY]);

        $section = ChurchSiteSection::query()->where('type', SectionTypes::GALLERY)->first();
        $this->actingAs($admin)
            ->put(route('site.homepage.sections.update', $section), [
                'media_ids' => [(string) $media->church_media_id],
                'enabled_draft' => '1',
            ]);

        $this->actingAs($admin)->post(route('site.homepage.publish'));

        $this->actingAs($admin)
            ->from(route('site.homepage.edit'))
            ->delete(route('site.media.destroy', $media))
            ->assertRedirect(route('site.homepage.edit'))
            ->assertSessionHasErrors();

        $this->assertNotNull(ChurchMedia::find($media->church_media_id));

        $this->actingAs($admin)->post(route('site.homepage.unpublish'));

        $this->actingAs($admin)
            ->delete(route('site.media.destroy', $media))
            ->assertRedirect(route('site.homepage.edit'));

        $this->assertNull(ChurchMedia::find($media->church_media_id));
        $this->assertDatabaseHas('activity_logs', [
            'route_name' => 'public_site.media_deleted',
            'user_id' => $admin->user_id,
        ]);
    }

    private function enablePublicSiteCapability(Church $church): void
    {
        ChurchCapability::query()->updateOrCreate(
            ['church_id' => $church->church_id, 'capability_key' => 'public_site'],
            ['enabled' => true, 'config' => []]
        );
        $church->unsetRelation('capabilities');
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRole(string $templateSlug): array
    {
        return $this->churchWithRoleOnChurch(Church::main(), $templateSlug);
    }

    /** @return array{0: Church, 1: \App\Models\User} */
    private function churchWithRoleOnChurch(Church $church, string $templateSlug): array
    {
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $templateSlug.'-t10c-'.$church->church_id.'@example.com']);

        ChurchUser::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        UserChurchRole::create([
            'church_id' => $church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $roles[$templateSlug]->role_id,
            'assigned_at' => now(),
        ]);

        return [$church, $user];
    }
}
