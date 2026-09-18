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

    public function test_leftover_returns_original_when_brief_fields_are_empty(): void
    {
        $project = new Project;
        $project->requirements = 'Visit the ward twice and write a report';

        $this->assertSame('Visit the ward twice and write a report', $project->leftoverLegacyRequirements());
    }

    public function test_leftover_is_null_when_composed_matches_requirements(): void
    {
        $project = new Project;
        $project->brief_purpose = 'Visit a family';
        $project->requirements = 'Visit a family';

        $this->assertNull($project->leftoverLegacyRequirements());
    }
}
