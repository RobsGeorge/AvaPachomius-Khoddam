<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\HomeVisit;
use App\Models\Person;
use App\Models\Priest;
use App\Models\Relationship;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Services\Maturity\GuardianshipService;
use App\Services\RoleTemplateService;
use App\Services\Visits\VisitNoteVisibility;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * ADR Slice 9 (regrounded): افتقاد occurrence (custodial) vs visit_notes (pastoral wall).
 */
class VisitNotesVisibilityTest extends EventModuleTestCase
{
    private Church $church;

    private VisitNoteVisibility $visibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::main();
        TenantContext::set($this->church);
        $this->visibility = app(VisitNoteVisibility::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_schema_adds_subject_morph_and_visit_notes_without_dropping_legacy(): void
    {
        $this->assertTrue(Schema::hasColumn('home_visit', 'subject_name'));
        $this->assertTrue(Schema::hasColumn('home_visit', 'address'));
        $this->assertTrue(Schema::hasColumn('home_visit', 'notes'));
        $this->assertTrue(Schema::hasColumn('home_visit', 'subject_type'));
        $this->assertTrue(Schema::hasColumn('home_visit', 'subject_id'));
        $this->assertTrue(Schema::hasTable('visit_notes'));
        $this->assertTrue(Schema::hasColumn('visit_notes', 'author_user_id'));
        $this->assertTrue(Schema::hasColumn('visit_notes', 'corrects_visit_note_id'));
    }

    public function test_guardian_sees_occurrence_but_never_notes(): void
    {
        [$guardian, , $ward] = $this->seedGuardianWard();
        [$servant] = $this->seedChurchMember('servant', 'hv-servant-a');
        $visit = $this->makeVisit($servant, $ward, 'Secret pastoral body');

        $this->assertTrue($this->visibility->canViewOccurrence($guardian, $visit));
        $this->assertFalse($this->visibility->canViewNote($guardian, $visit->pastoralNotes()->first()));
        $this->assertCount(0, $this->visibility->visibleNotesFor($guardian, $visit));
    }

    public function test_author_and_priest_see_notes_unrelated_servant_does_not(): void
    {
        [, , $ward] = $this->seedGuardianWard();
        [$author] = $this->seedChurchMember('servant', 'hv-author');
        [$other] = $this->seedChurchMember('servant', 'hv-other');
        [$priest] = $this->seedChurchMember('priest', 'hv-priest');

        $visit = $this->makeVisit($author, $ward, 'Pastoral observation');
        $note = $visit->pastoralNotes()->first();

        $this->assertTrue($this->visibility->canViewNote($author, $note));
        $this->assertTrue($this->visibility->canViewNote($priest, $note));
        $this->assertFalse($this->visibility->canViewNote($other, $note));
        $this->assertFalse($this->visibility->canViewOccurrence($other, $visit));
    }

    public function test_assignee_who_did_not_author_can_still_read_notes(): void
    {
        [, , $ward] = $this->seedGuardianWard();
        [$assignee] = $this->seedChurchMember('servant', 'hv-assignee');
        [$priest] = $this->seedChurchMember('priest', 'hv-priest-author');

        $visit = $this->makeVisit($assignee, $ward);
        $note = $this->visibility->appendNote($visit, $priest, 'Priest wrote while assignee visits');

        $this->assertTrue($this->visibility->canViewNote($assignee, $note));
        $this->assertTrue($this->visibility->canViewNote($priest, $note));
    }

    public function test_safeguarding_restricted_hides_notes_from_assigned_servant(): void
    {
        [$guardian, , $ward, $edge] = $this->seedGuardianWard();
        [$servant] = $this->seedChurchMember('servant', 'hv-risk-servant');
        [$priest] = $this->seedChurchMember('priest', 'hv-safe-priest');

        // Note exists before the safeguarding flag — servant authored it while allowed.
        $visit = $this->makeVisit($servant, $ward, 'Sensitive safeguarding note');
        $note = $visit->pastoralNotes()->first();

        $actor = $this->createUser(['email' => 'vis-manager@example.com', 'is_superadmin' => true]);
        app(GuardianshipService::class)->setGuardianVisibility(
            $edge,
            Relationship::VISIBILITY_RESTRICTED,
            $actor
        );

        $this->assertTrue($this->visibility->subjectIsSafeguardingRestricted($visit));
        $this->assertFalse($this->visibility->canViewNote($servant, $note));
        $this->assertFalse($this->visibility->canViewNote($guardian, $note));
        $this->assertTrue($this->visibility->canViewNote($priest, $note));
        $this->assertTrue($this->visibility->canViewOccurrence($guardian, $visit));
        $this->assertFalse($this->visibility->canCreateNote($servant, $visit));
        $this->assertTrue($this->visibility->canCreateNote($priest, $visit));
    }

    public function test_visit_notes_are_append_only(): void
    {
        [, , $ward] = $this->seedGuardianWard();
        [$servant] = $this->seedChurchMember('servant', 'hv-append');
        $visit = $this->makeVisit($servant, $ward, 'Original');
        $note = $visit->pastoralNotes()->first();

        $this->expectException(\LogicException::class);
        $note->body = 'tampered';
        $note->save();
    }

    public function test_legacy_free_text_subject_and_notes_preserved_on_existing_rows(): void
    {
        [$servant] = $this->seedChurchMember('servant', 'hv-legacy');
        $visit = new HomeVisit([
            'assigned_user_id' => $servant->user_id,
            'subject_name' => 'عائلة محفوظة',
            'address' => 'شارع قديم 12',
            'notes' => 'ملاحظات جدولة قديمة',
            'scheduled_at' => now()->addDay(),
            'status' => HomeVisit::STATUS_SCHEDULED,
        ]);
        $visit->church_id = $this->church->church_id;
        $visit->save();

        $fresh = HomeVisit::query()->findOrFail($visit->home_visit_id);
        $this->assertSame('عائلة محفوظة', $fresh->subject_name);
        $this->assertSame('شارع قديم 12', $fresh->address);
        $this->assertSame('ملاحظات جدولة قديمة', $fresh->notes);
        $this->assertNull($fresh->subject_type);
        $this->assertNull($fresh->subject_id);
    }

    public function test_edit_shows_pastoral_notes_to_assignee_not_leaked_via_index(): void
    {
        [, , $ward] = $this->seedGuardianWard();
        [$servant] = $this->seedChurchMember('servant', 'hv-edit-ui');
        $visit = $this->makeVisit($servant, $ward, 'Only on edit screen');

        $this->actingAs($servant)
            ->get(route('church.home-visits.edit', $visit))
            ->assertOk()
            ->assertSee('Only on edit screen', false);

        $this->actingAs($servant)
            ->get(route('church.home-visits.index'))
            ->assertOk()
            ->assertDontSee('Only on edit screen');
    }

    /** @return array{0: User} */
    private function seedChurchMember(string $templateSlug, string $emailPrefix): array
    {
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($this->church);
        $user = $this->createUser(['email' => $emailPrefix.'@example.com']);

        ChurchUser::create([
            'church_id' => $this->church->church_id,
            'user_id' => $user->user_id,
            'status' => 'active',
            'joined_at' => now(),
        ]);
        UserChurchRole::create([
            'church_id' => $this->church->church_id,
            'user_id' => $user->user_id,
            'role_id' => $roles[$templateSlug]->role_id,
            'assigned_at' => now(),
        ]);

        if ($templateSlug === 'priest') {
            Priest::query()->create([
                'church_id' => $this->church->church_id,
                'user_id' => $user->user_id,
                'title' => 'أبونا',
                'status' => 'active',
            ]);
        }

        return [$user->fresh()];
    }

    /**
     * @return array{0: User, 1: Person, 2: Person, 3: Relationship}
     */
    private function seedGuardianWard(): array
    {
        $guardianUser = $this->createUser([
            'email' => 'guardian-'.uniqid().'@example.com',
            'date_of_birth' => '1980-01-01',
        ]);
        $guardianPerson = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'وصي',
            'second_name' => 'زيارة',
            'third_name' => 'أ',
            'date_of_birth' => '1980-01-01',
            'is_minor' => false,
        ]);
        $guardianUser->forceFill(['person_id' => $guardianPerson->person_id])->save();

        $ward = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'قاصر',
            'second_name' => 'زيارة',
            'third_name' => 'ب',
            'date_of_birth' => '2015-01-01',
            'is_minor' => true,
        ]);

        $verifier = $this->createUser(['email' => 'verifier-'.uniqid().'@example.com']);
        $edge = app(GuardianshipService::class)->linkGuardian(
            $guardianPerson,
            $ward,
            $verifier,
            $this->church
        );

        return [$guardianUser->fresh(), $guardianPerson, $ward, $edge];
    }

    private function makeVisit(User $assignee, Person $subject, ?string $pastoralBody = null): HomeVisit
    {
        $visit = new HomeVisit([
            'assigned_user_id' => $assignee->user_id,
            'subject_type' => VisitNoteVisibility::SUBJECT_PERSON,
            'subject_id' => $subject->person_id,
            'subject_name' => trim($subject->first_name.' '.$subject->second_name),
            'scheduled_at' => now()->addDays(2),
            'status' => HomeVisit::STATUS_SCHEDULED,
            'notes' => 'Logistics only',
        ]);
        $visit->church_id = $this->church->church_id;
        $visit->save();

        if ($pastoralBody !== null) {
            $this->visibility->appendNote($visit, $assignee, $pastoralBody);
        }

        return $visit->fresh();
    }
}
