<?php

namespace Tests\Feature\People;

use App\Models\Church;
use App\Models\Contact;
use App\Models\Person;
use App\Models\Relationship;
use App\Services\People\FamilyNeighborhoodService;
use App\Services\People\MarriageService;
use App\Services\People\PersonRegistryService;
use App\Services\People\ResidenceService;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;
use Tests\Support\EventModuleTestCase;

/**
 * ADR Slice 7 (regrounded): dated family edges, residence, non-unique contacts,
 * neighborhood query. Family/FamilyMember household container soft-deprecated.
 */
class FamilyGraphTest extends EventModuleTestCase
{
    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();
        $this->church = Church::main();
        TenantContext::set($this->church);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_schema_has_residences_and_contacts_without_value_unique(): void
    {
        $this->assertTrue(Schema::hasTable('residences'));
        $this->assertTrue(Schema::hasTable('residence_members'));
        $this->assertTrue(Schema::hasTable('contacts'));
        $this->assertTrue(Schema::hasColumn('relationships', 'start_date'));
        $this->assertTrue(Schema::hasColumn('relationships', 'end_date'));

        // SQLite: list unique indexes; none may include `value`.
        $uniqueIndexes = collect(Schema::getConnection()->select(
            "PRAGMA index_list('contacts')"
        ))->where('unique', 1);

        foreach ($uniqueIndexes as $index) {
            $cols = collect(Schema::getConnection()->select(
                'PRAGMA index_info('.$this->quoteIdent($index->name).')'
            ))->pluck('name');
            $this->assertFalse(
                $cols->contains('value'),
                'contacts must not have a unique constraint involving value: '.$index->name
            );
        }
    }

    private function quoteIdent(string $name): string
    {
        return "'".str_replace("'", "''", $name)."'";
    }

    public function test_marriage_adds_exactly_one_edge_and_leaves_others_untouched(): void
    {
        $a = $this->makePerson('أ');
        $b = $this->makePerson('ب');
        $childA = $this->makePerson('طفل-أ');
        $sibB = $this->makePerson('أخ-ب');

        $preExisting = [
            Relationship::withoutTenancy()->create([
                'church_id' => $this->church->church_id,
                'person_id' => $childA->person_id,
                'related_person_id' => $a->person_id,
                'type' => Relationship::TYPE_CHILD_OF,
                'start_date' => '2010-01-01',
            ]),
            Relationship::withoutTenancy()->create([
                'church_id' => $this->church->church_id,
                'person_id' => $b->person_id,
                'related_person_id' => $sibB->person_id,
                'type' => Relationship::TYPE_SIBLING_OF,
                'start_date' => '2005-01-01',
            ]),
        ];

        $beforeRows = Relationship::withoutTenancy()
            ->orderBy('relationship_id')
            ->get()
            ->map(fn (Relationship $r) => [
                'relationship_id' => (int) $r->relationship_id,
                'person_id' => (int) $r->person_id,
                'related_person_id' => (int) $r->related_person_id,
                'type' => $r->type,
                'start_date' => optional($r->start_date)?->toDateString(),
                'end_date' => optional($r->end_date)?->toDateString(),
            ])
            ->all();
        $beforeCount = count($beforeRows);
        $beforeIds = collect($beforeRows)->pluck('relationship_id')->all();

        $edge = app(MarriageService::class)->marry($a, $b, $this->createUser());

        $this->assertSame(Relationship::TYPE_SPOUSE_OF, $edge->type);
        $this->assertSame($beforeCount + 1, Relationship::withoutTenancy()->count());

        foreach ($preExisting as $rel) {
            $fresh = Relationship::withoutTenancy()->findOrFail($rel->relationship_id);
            $this->assertSame((int) $rel->person_id, (int) $fresh->person_id);
            $this->assertSame((int) $rel->related_person_id, (int) $fresh->related_person_id);
            $this->assertSame($rel->type, $fresh->type);
            $this->assertNull($fresh->end_date);
        }

        $afterPrior = Relationship::withoutTenancy()
            ->whereIn('relationship_id', $beforeIds)
            ->orderBy('relationship_id')
            ->get()
            ->map(fn (Relationship $r) => [
                'relationship_id' => (int) $r->relationship_id,
                'person_id' => (int) $r->person_id,
                'related_person_id' => (int) $r->related_person_id,
                'type' => $r->type,
                'start_date' => optional($r->start_date)?->toDateString(),
                'end_date' => optional($r->end_date)?->toDateString(),
            ])
            ->all();

        $this->assertSame($beforeRows, $afterPrior);
    }

    public function test_shared_landline_and_mobile_across_people_and_residences_allowed(): void
    {
        $p1 = $this->makePerson('م١');
        $p2 = $this->makePerson('م٢');
        $residences = app(ResidenceService::class);
        $home = $residences->createResidence([
            'church_id' => $this->church->church_id,
            'address' => 'شارع الكنيسة ١',
        ], $this->church);
        $other = $residences->createResidence([
            'church_id' => $this->church->church_id,
            'address' => 'شارع الكنيسة ٢',
        ], $this->church);

        $sharedLandline = '0225551234';
        $sharedMobile = '01001234567';

        try {
            Contact::withoutTenancy()->create([
                'church_id' => $this->church->church_id,
                'contactable_type' => Contact::CONTACTABLE_PERSON,
                'contactable_id' => $p1->person_id,
                'type' => Contact::TYPE_LANDLINE,
                'value' => $sharedLandline,
            ]);
            Contact::withoutTenancy()->create([
                'church_id' => $this->church->church_id,
                'contactable_type' => Contact::CONTACTABLE_PERSON,
                'contactable_id' => $p2->person_id,
                'type' => Contact::TYPE_LANDLINE,
                'value' => $sharedLandline,
            ]);
            Contact::withoutTenancy()->create([
                'church_id' => $this->church->church_id,
                'contactable_type' => Contact::CONTACTABLE_RESIDENCE,
                'contactable_id' => $home->residence_id,
                'type' => Contact::TYPE_LANDLINE,
                'value' => $sharedLandline,
            ]);
            Contact::withoutTenancy()->create([
                'church_id' => $this->church->church_id,
                'contactable_type' => Contact::CONTACTABLE_RESIDENCE,
                'contactable_id' => $other->residence_id,
                'type' => Contact::TYPE_LANDLINE,
                'value' => $sharedLandline,
            ]);
            Contact::withoutTenancy()->create([
                'church_id' => $this->church->church_id,
                'contactable_type' => Contact::CONTACTABLE_PERSON,
                'contactable_id' => $p1->person_id,
                'type' => Contact::TYPE_MOBILE,
                'value' => $sharedMobile,
            ]);
            Contact::withoutTenancy()->create([
                'church_id' => $this->church->church_id,
                'contactable_type' => Contact::CONTACTABLE_PERSON,
                'contactable_id' => $p2->person_id,
                'type' => Contact::TYPE_MOBILE,
                'value' => $sharedMobile,
            ]);
        } catch (QueryException $e) {
            $this->fail('Shared contacts must not hit a unique constraint: '.$e->getMessage());
        }

        $this->assertSame(4, Contact::withoutTenancy()->where('value', $sharedLandline)->count());
        $this->assertSame(2, Contact::withoutTenancy()->where('value', $sharedMobile)->count());
    }

    public function test_residence_move_out_ends_membership_without_touching_relationships(): void
    {
        $person = $this->makePerson('ساكن');
        $spouse = $this->makePerson('زوج');
        app(MarriageService::class)->marry($person, $spouse);

        $relSnapshot = Relationship::withoutTenancy()
            ->orderBy('relationship_id')
            ->get(['relationship_id', 'person_id', 'related_person_id', 'type', 'start_date', 'end_date'])
            ->map(fn (Relationship $r) => [
                'relationship_id' => (int) $r->relationship_id,
                'person_id' => (int) $r->person_id,
                'related_person_id' => (int) $r->related_person_id,
                'type' => $r->type,
                'start_date' => optional($r->start_date)?->toDateString(),
                'end_date' => optional($r->end_date)?->toDateString(),
            ])
            ->all();

        $service = app(ResidenceService::class);
        $from = $service->createResidence([
            'church_id' => $this->church->church_id,
            'address' => 'المنزل القديم',
        ], $this->church);
        $to = $service->createResidence([
            'church_id' => $this->church->church_id,
            'address' => 'المنزل الجديد',
        ], $this->church);
        $service->addMember($from, $person, '2020-01-01');

        $result = $service->moveOut($person, $from, $to, '2024-06-01', '2024-06-01');

        $this->assertNotNull($result['ended']->end_date);
        $this->assertSame('2024-06-01', $result['ended']->end_date->toDateString());
        $this->assertNotNull($result['started']);
        $this->assertSame((int) $to->residence_id, (int) $result['started']->residence_id);
        $this->assertNull($result['started']->end_date);

        $after = Relationship::withoutTenancy()
            ->orderBy('relationship_id')
            ->get(['relationship_id', 'person_id', 'related_person_id', 'type', 'start_date', 'end_date'])
            ->map(fn (Relationship $r) => [
                'relationship_id' => (int) $r->relationship_id,
                'person_id' => (int) $r->person_id,
                'related_person_id' => (int) $r->related_person_id,
                'type' => $r->type,
                'start_date' => optional($r->start_date)?->toDateString(),
                'end_date' => optional($r->end_date)?->toDateString(),
            ])
            ->all();
        $this->assertSame($relSnapshot, $after);
    }

    public function test_neighborhood_returns_connected_set_and_excludes_ended_edges(): void
    {
        $a = $this->makePerson('نواة');
        $spouse = $this->makePerson('زوج');
        $child = $this->makePerson('ابن');
        $exSibling = $this->makePerson('منتهي');

        app(MarriageService::class)->marry($a, $spouse);
        Relationship::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'person_id' => $child->person_id,
            'related_person_id' => $a->person_id,
            'type' => Relationship::TYPE_CHILD_OF,
            'start_date' => '2015-01-01',
        ]);
        Relationship::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'person_id' => $a->person_id,
            'related_person_id' => $exSibling->person_id,
            'type' => Relationship::TYPE_SIBLING_OF,
            'start_date' => '2000-01-01',
            'end_date' => '2020-01-01',
        ]);

        $neighborhood = app(FamilyNeighborhoodService::class)->forPerson($a);
        $ids = $neighborhood->keys()->map(fn ($id) => (int) $id)->sort()->values()->all();

        $this->assertSame(
            collect([$a->person_id, $spouse->person_id, $child->person_id])->map(fn ($id) => (int) $id)->sort()->values()->all(),
            $ids
        );
        $this->assertFalse($neighborhood->has($exSibling->person_id));

        $withEnded = app(FamilyNeighborhoodService::class)->forPerson($a, true);
        $this->assertTrue($withEnded->has($exSibling->person_id));
    }

    private function makePerson(string $label): Person
    {
        static $n = 0;
        $n++;

        return app(PersonRegistryService::class)->createPerson([
            'church_id' => $this->church->church_id,
            'first_name' => $label,
            'second_name' => 'اختبار',
            'third_name' => (string) $n,
            'display_name' => $label.' اختبار '.$n,
        ], true);
    }
}
