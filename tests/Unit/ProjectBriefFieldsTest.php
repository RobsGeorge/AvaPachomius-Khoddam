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
}
