<?php

namespace App\Services\PublicSite;

use App\Models\Church;
use App\Models\ChurchMedia;
use App\Models\ChurchSite;
use App\Models\ChurchSiteSection;
use App\Services\AuditLogService;
use App\Support\PublicSite\SectionTypes;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChurchSiteService
{
    public function ensureSiteForChurch(Church $church): ChurchSite
    {
        $site = ChurchSite::query()->where('church_id', $church->church_id)->first();
        if ($site instanceof ChurchSite) {
            return $site;
        }

        return ChurchSite::create([
            'church_id' => $church->church_id,
            'theme_draft' => [],
            'theme_published' => null,
        ]);
    }

    /** @return Collection<int, ChurchSiteSection> */
    public function draftSections(ChurchSite $site): Collection
    {
        return $site->sections()
            ->where('enabled_draft', true)
            ->orderBy('sort_order')
            ->get();
    }

    /** @return Collection<int, ChurchSiteSection> */
    public function publishedSections(ChurchSite $site): Collection
    {
        return $site->sections()
            ->where('enabled_published', true)
            ->orderBy('sort_order')
            ->get();
    }

    public function createSection(ChurchSite $site, string $type): ChurchSiteSection
    {
        if (! in_array($type, SectionTypes::all(), true)) {
            throw ValidationException::withMessages(['type' => __('public_site.invalid_section_type')]);
        }

        if ($site->sections()->count() >= SectionTypes::MAX_SECTIONS) {
            throw ValidationException::withMessages(['type' => __('public_site.max_sections_reached')]);
        }

        if ($type === SectionTypes::HERO && $site->sections()->where('type', SectionTypes::HERO)->exists()) {
            throw ValidationException::withMessages(['type' => __('public_site.hero_max_one')]);
        }

        $maxOrder = (int) $site->sections()->max('sort_order');

        return ChurchSiteSection::create([
            'church_id' => $site->church_id,
            'church_site_id' => $site->church_site_id,
            'type' => $type,
            'sort_order' => $maxOrder + 1,
            'enabled_draft' => true,
            'enabled_published' => false,
            'content_draft' => SectionTypes::defaults($type),
            'content_published' => null,
        ]);
    }

    /** @param  array<string, mixed>  $input */
    public function updateSection(ChurchSiteSection $section, array $input): ChurchSiteSection
    {
        $rules = SectionTypes::validationRules($section->type);
        $validated = validator($input, $rules)->validate();

        $section->content_draft = SectionTypes::normalizeContent($section->type, $validated);
        if (array_key_exists('enabled_draft', $input)) {
            $section->enabled_draft = filter_var($input['enabled_draft'], FILTER_VALIDATE_BOOLEAN);
        }
        $section->save();

        return $section->fresh();
    }

    public function deleteSection(ChurchSiteSection $section): void
    {
        $section->delete();
    }

    /** @param  list<int>  $orderedIds */
    public function reorderSections(ChurchSite $site, array $orderedIds): void
    {
        $owned = $site->sections()->pluck('church_site_section_id')->all();
        $orderedIds = array_values(array_intersect(array_map('intval', $orderedIds), $owned));

        if (count($orderedIds) !== count($owned)) {
            throw ValidationException::withMessages(['order' => __('public_site.reorder_invalid')]);
        }

        DB::transaction(function () use ($orderedIds): void {
            foreach ($orderedIds as $index => $sectionId) {
                ChurchSiteSection::where('church_site_section_id', $sectionId)
                    ->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function publish(ChurchSite $site): ChurchSite
    {
        DB::transaction(function () use ($site): void {
            $site->refresh();
            foreach ($site->sections as $section) {
                $section->content_published = $section->enabled_draft
                    ? $section->content_draft
                    : null;
                $section->enabled_published = (bool) $section->enabled_draft;
                $section->save();
            }

            $site->theme_published = $site->theme_draft ?? [];
            $site->published_at = now();
            $site->published_by = Auth::id();
            $site->save();
        });

        AuditLogService::recordEvent('public_site.published', [
            'church_id' => $site->church_id,
            'church_site_id' => $site->church_site_id,
            'actor_user_id' => Auth::id(),
        ]);

        return $site->fresh(['sections']);
    }

    public function unpublish(ChurchSite $site): ChurchSite
    {
        DB::transaction(function () use ($site): void {
            $site->published_at = null;
            $site->published_by = null;
            $site->theme_published = null;
            $site->save();

            foreach ($site->sections as $section) {
                $section->enabled_published = false;
                $section->content_published = null;
                $section->save();
            }
        });

        AuditLogService::recordEvent('public_site.unpublished', [
            'church_id' => $site->church_id,
            'church_site_id' => $site->church_site_id,
            'actor_user_id' => Auth::id(),
        ]);

        return $site->fresh(['sections']);
    }

    public function mediaReferencedInPublished(int $mediaId): bool
    {
        $sections = ChurchSiteSection::query()
            ->where('enabled_published', true)
            ->get();

        foreach ($sections as $section) {
            $content = $section->content_published ?? [];
            if ($this->contentReferencesMedia($section->type, $content, $mediaId)) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $content */
    public function contentReferencesMedia(string $type, array $content, int $mediaId): bool
    {
        if (in_array($type, [SectionTypes::HERO, SectionTypes::ABOUT], true)) {
            return (int) ($content['image_media_id'] ?? 0) === $mediaId;
        }

        if ($type === SectionTypes::GALLERY) {
            $ids = $content['media_ids'] ?? [];

            return in_array($mediaId, array_map('intval', is_array($ids) ? $ids : []), true);
        }

        return false;
    }

    public function deleteMedia(ChurchMedia $media): void
    {
        if ($this->mediaReferencedInPublished($media->church_media_id)) {
            throw ValidationException::withMessages(['media' => __('public_site.media_in_use')]);
        }

        Storage::disk('public')->delete($media->path);

        AuditLogService::recordEvent('public_site.media_deleted', [
            'church_id' => $media->church_id,
            'church_media_id' => $media->church_media_id,
            'actor_user_id' => Auth::id(),
        ]);

        $media->delete();
    }
}
