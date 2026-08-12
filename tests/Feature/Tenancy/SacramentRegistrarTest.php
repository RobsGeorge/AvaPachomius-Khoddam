<?php

namespace Tests\Feature\Tenancy;

use App\Models\Person;
use App\Models\Relationship;
use App\Models\Sacrament;
use App\Services\Sacraments\SacramentRepository;
use App\Support\Sacraments\SacramentDateFormatter;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;
use ReflectionClass;
use Tests\Support\EventModuleTestCase;

class SacramentRegistrarTest extends EventModuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON');
        }
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_repository_has_no_update_or_delete_methods(): void
    {
        $reflection = new ReflectionClass(SacramentRepository::class);
        $publicMethods = array_map(
            static fn ($method) => $method->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC)
        );

        $this->assertContains('record', $publicMethods);
        $this->assertContains('correct', $publicMethods);
        $this->assertNotContains('update', $publicMethods);
        $this->assertNotContains('delete', $publicMethods);
        $this->assertFalse(method_exists(SacramentRepository::class, 'update'));
        $this->assertFalse(method_exists(SacramentRepository::class, 'delete'));
    }

    public function test_model_update_and_delete_throw(): void
    {
        [$church, $recorder, $person] = $this->seedChurchActorPerson();
        TenantContext::set($church);

        $sacrament = app(SacramentRepository::class)->record([
            'church_id' => (int) $church->church_id,
            'person_id' => (int) $person->person_id,
            'type' => Sacrament::TYPE_BAPTISM,
            'date' => '1990-05-12',
            'date_precision' => Sacrament::PRECISION_DAY,
            'recorded_by' => (int) $recorder->user_id,
        ]);

        try {
            $sacrament->update(['location_text' => 'mutated']);
            $this->fail('Expected update to throw');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        try {
            $sacrament->delete();
            $this->fail('Expected delete to throw');
        } catch (LogicException $e) {
            $this->assertStringContainsString('append-only', $e->getMessage());
        }

        $this->assertDatabaseHas('sacraments', [
            'sacrament_id' => $sacrament->sacrament_id,
            'location_text' => null,
        ]);
    }

    public function test_correction_creates_new_row_leaving_original_untouched(): void
    {
        [$church, $recorder, $person] = $this->seedChurchActorPerson();
        TenantContext::set($church);
        $repo = app(SacramentRepository::class);

        $original = $repo->record([
            'church_id' => (int) $church->church_id,
            'person_id' => (int) $person->person_id,
            'type' => Sacrament::TYPE_MARRIAGE,
            'date' => '2010-06-20',
            'date_precision' => Sacrament::PRECISION_DAY,
            'location_text' => 'Original church hall',
            'recorded_by' => (int) $recorder->user_id,
        ]);

        $originalSnapshot = $original->getAttributes();

        $correction = $repo->correct((int) $original->sacrament_id, [
            'date' => '2010-06-21',
            'location_text' => 'Corrected location',
            'recorded_by' => (int) $recorder->user_id,
        ]);

        $this->assertNotSame((int) $original->sacrament_id, (int) $correction->sacrament_id);
        $this->assertSame((int) $original->sacrament_id, (int) $correction->corrects_sacrament_id);
        $this->assertSame('Corrected location', $correction->location_text);

        $reloaded = Sacrament::query()->whereKey($original->sacrament_id)->first();
        $this->assertNotNull($reloaded);
        $this->assertSame('Original church hall', $reloaded->location_text);
        $this->assertSame('2010-06-20', $reloaded->date->format('Y-m-d'));
        $this->assertNull($reloaded->corrects_sacrament_id);

        foreach (['type', 'date', 'date_precision', 'location_text', 'person_id', 'recorded_by'] as $key) {
            $expected = $originalSnapshot[$key] ?? null;
            $actual = $reloaded->getAttributes()[$key] ?? null;
            if ($key === 'date') {
                $this->assertSame(
                    substr((string) $expected, 0, 10),
                    substr((string) $actual, 0, 10)
                );
            } else {
                $this->assertEquals($expected, $actual);
            }
        }
    }

    public function test_repose_sets_deceased_at_and_keeps_child_relationship(): void
    {
        [$church, $recorder, $parent] = $this->seedChurchActorPerson('Late', 'Parent');
        TenantContext::set($church);

        $child = Person::query()->create([
            'church_id' => $church->church_id,
            'first_name' => 'Living',
            'second_name' => 'Child',
            'email' => 'living-child@example.com',
            'mobile_number' => '01099990001',
        ]);

        $edge = Relationship::query()->create([
            'church_id' => $church->church_id,
            'person_id' => $parent->person_id,
            'related_person_id' => $child->person_id,
            'type' => 'child',
        ]);

        $this->assertNull($edge->end_date);

        $repo = app(SacramentRepository::class);
        $repo->record([
            'church_id' => (int) $church->church_id,
            'person_id' => (int) $parent->person_id,
            'type' => Sacrament::TYPE_REPOSE,
            'date' => '2024-03-15',
            'date_precision' => Sacrament::PRECISION_DAY,
            'recorded_by' => (int) $recorder->user_id,
        ]);

        $parent->refresh();
        $this->assertNotNull($parent->deceased_at);
        $this->assertTrue($parent->isDeceased());
        $this->assertNotNull(Person::query()->whereKey($parent->person_id)->first());

        $edge->refresh();
        $this->assertNull($edge->end_date);

        $childrenOfLateY = Relationship::query()
            ->where('person_id', $parent->person_id)
            ->where('type', 'child')
            ->whereNull('end_date')
            ->pluck('related_person_id')
            ->all();

        $this->assertSame([(int) $child->person_id], array_map('intval', $childrenOfLateY));
    }

    public function test_year_precision_display_never_implies_day_or_month(): void
    {
        [$church, $recorder, $person] = $this->seedChurchActorPerson();
        TenantContext::set($church);

        $sacrament = app(SacramentRepository::class)->record([
            'church_id' => (int) $church->church_id,
            'person_id' => (int) $person->person_id,
            'type' => Sacrament::TYPE_BAPTISM,
            'date' => '1985-07-19',
            'date_precision' => Sacrament::PRECISION_YEAR,
            'recorded_by' => (int) $recorder->user_id,
        ]);

        $this->assertSame('1985-01-01', $sacrament->date->format('Y-m-d'));

        $formattedEn = SacramentDateFormatter::format($sacrament, 'en');
        $formattedAr = SacramentDateFormatter::format($sacrament, 'ar');

        $this->assertSame('1985', $formattedEn);
        $this->assertSame('1985', $formattedAr);
        // Must not invent the recorded month/day (07 / 19) or ISO date padding.
        $this->assertDoesNotMatchRegularExpression('/-0?7\b/', $formattedEn);
        $this->assertDoesNotMatchRegularExpression('/\b19\b/', $formattedEn);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}/', $formattedEn);
        $this->assertDoesNotMatchRegularExpression('/\d{4}-\d{2}-\d{2}/', $formattedEn);
        $this->assertDoesNotMatchRegularExpression('/January|July|يناير|يوليو/i', $formattedEn.$formattedAr);
    }

    public function test_person_id_foreign_key_rejects_nonexistent_person(): void
    {
        [$church, $recorder] = $this->seedChurchActor();
        TenantContext::set($church);

        $this->expectException(QueryException::class);

        DB::table('sacraments')->insert([
            'church_id' => (int) $church->church_id,
            'person_id' => 9_999_999,
            'type' => Sacrament::TYPE_BAPTISM,
            'date' => '2000-01-01',
            'date_precision' => Sacrament::PRECISION_DAY,
            'recorded_by' => (int) $recorder->user_id,
            'recorded_at' => now()->toDateTimeString(),
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    public function test_tenant_isolation_hides_other_church_sacraments(): void
    {
        [$churchA, $recorderA, $personA] = $this->seedChurchActorPerson('Alpha', 'One');
        [$churchB, $recorderB, $personB] = $this->seedChurchActorPerson('Beta', 'Two');

        TenantContext::set($churchA);
        $sacramentA = app(SacramentRepository::class)->record([
            'church_id' => (int) $churchA->church_id,
            'person_id' => (int) $personA->person_id,
            'type' => Sacrament::TYPE_BAPTISM,
            'date' => '2001-01-01',
            'date_precision' => Sacrament::PRECISION_DAY,
            'recorded_by' => (int) $recorderA->user_id,
        ]);

        TenantContext::set($churchB);
        app(SacramentRepository::class)->record([
            'church_id' => (int) $churchB->church_id,
            'person_id' => (int) $personB->person_id,
            'type' => Sacrament::TYPE_BAPTISM,
            'date' => '2002-01-01',
            'date_precision' => Sacrament::PRECISION_DAY,
            'recorded_by' => (int) $recorderB->user_id,
        ]);

        TenantContext::set($churchB);

        $this->assertNull(Sacrament::query()->whereKey($sacramentA->sacrament_id)->first());
        $this->assertSame(1, Sacrament::query()->count());
    }

    /**
     * @return array{0: \App\Models\Church, 1: \App\Models\User, 2: Person}
     */
    private function seedChurchActorPerson(string $first = 'Test', string $second = 'Person'): array
    {
        [$church, $recorder] = $this->seedChurchActor();

        $person = Person::query()->create([
            'church_id' => $church->church_id,
            'first_name' => $first,
            'second_name' => $second,
            'email' => strtolower($first).'-'.uniqid().'@example.com',
            'mobile_number' => '010'.str_pad((string) random_int(1000000, 9999999), 7, '0', STR_PAD_LEFT),
        ]);

        return [$church, $recorder, $person];
    }

    /**
     * @return array{0: \App\Models\Church, 1: \App\Models\User}
     */
    private function seedChurchActor(): array
    {
        $church = $this->createChurch();
        $recorder = $this->createUser();

        return [$church, $recorder];
    }
}
