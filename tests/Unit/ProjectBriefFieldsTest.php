<?php

namespace Tests\Unit;

use App\Models\Project;
use Tests\TestCase;

class ProjectBriefFieldsTest extends TestCase
{
    public function test_compose_requirements_joins_filled_brief_values(): void
    {
        $this->assertSame(
            "Main work\nYouth\nHall",
            Project::composeRequirements([
                Project::BRIEF_MAIN_TITLE => 'Main work',
                Project::BRIEF_AUDIENCE => 'Youth',
                Project::BRIEF_ENVIRONMENT => 'Hall',
                Project::BRIEF_PURPOSE => '',
            ])
        );
        $this->assertNull(Project::composeRequirements([
            Project::BRIEF_MAIN_TITLE => '  ',
            Project::BRIEF_AUDIENCE => null,
        ]));
    }

    public function test_brief_from_row_trims_and_caps_at_255(): void
    {
        $row = Project::briefFromRow([
            'brief_main_title' => '  Hello  ',
            'brief_purpose' => str_repeat('x', 300),
        ]);

        $this->assertSame('Hello', $row[Project::BRIEF_MAIN_TITLE]);
        $this->assertNull($row[Project::BRIEF_AUDIENCE]);
        $this->assertSame(255, mb_strlen((string) $row[Project::BRIEF_PURPOSE]));
    }

    public function test_legacy_paragraph_maps_to_purpose_only(): void
    {
        $mapped = Project::briefFromLegacyRequirements("  Visit families twice.\n");

        $this->assertSame(['brief_purpose' => 'Visit families twice.'], $mapped);
    }

    public function test_legacy_two_to_four_lines_map_in_order(): void
    {
        $mapped = Project::briefFromLegacyRequirements("Main work\nYouth\nHall\nAnnounce");

        $this->assertSame([
            Project::BRIEF_MAIN_TITLE => 'Main work',
            Project::BRIEF_AUDIENCE => 'Youth',
            Project::BRIEF_ENVIRONMENT => 'Hall',
            Project::BRIEF_PURPOSE => 'Announce',
        ], $mapped);

        $two = Project::briefFromLegacyRequirements("Title line\nAudience line");
        $this->assertSame('Title line', $two[Project::BRIEF_MAIN_TITLE]);
        $this->assertSame('Audience line', $two[Project::BRIEF_AUDIENCE]);
        $this->assertArrayNotHasKey(Project::BRIEF_PURPOSE, $two);
    }

    public function test_legacy_five_lines_stay_in_purpose(): void
    {
        $text = "a\nb\nc\nd\ne";
        $mapped = Project::briefFromLegacyRequirements($text);

        $this->assertSame(['brief_purpose' => $text], $mapped);
    }

    public function test_legacy_prefill_truncates_purpose_at_255_and_keeps_leftover(): void
    {
        $project = new Project;
        $project->requirements = str_repeat('x', 257);

        $project->applyLegacyBriefPrefill();

        $this->assertSame(255, mb_strlen((string) $project->brief_purpose));
        $this->assertSame(str_repeat('x', 257), $project->requirements);
        $this->assertSame(str_repeat('x', 257), $project->leftoverLegacyRequirements());
    }

    public function test_apply_legacy_prefill_does_not_overwrite_existing_brief(): void
    {
        $project = new Project;
        $project->brief_purpose = 'Already set';
        $project->requirements = 'Old description';

        $project->applyLegacyBriefPrefill();

        $this->assertSame('Already set', $project->brief_purpose);
        $this->assertSame('Old description', $project->requirements);
    }

    public function test_leftover_is_null_when_composed_matches_requirements(): void
    {
        $project = new Project;
        $project->brief_purpose = 'Visit a family';
        $project->requirements = 'Visit a family';

        $this->assertNull($project->leftoverLegacyRequirements());
    }
}
