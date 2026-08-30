<?php

namespace Tests\Feature\Tenancy;

use App\Models\AccessLedgerEntry;
use App\Models\Church;
use App\Models\ChurchUser;
use App\Models\Contact;
use App\Models\Document;
use App\Models\HomeVisit;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Priest;
use App\Models\Relationship;
use App\Models\Residence;
use App\Models\Sacrament;
use App\Models\User;
use App\Models\UserChurchRole;
use App\Services\Documents\DocumentEnvelopeEncryption;
use App\Services\Documents\DocumentRepository;
use App\Services\Documents\DocumentStorage;
use App\Services\Documents\DocumentVisibility;
use App\Services\Maturity\GuardianshipService;
use App\Services\RoleTemplateService;
use App\Services\Sacraments\SacramentRepository;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\EventModuleTestCase;

/**
 * ADR Slice 10 (regrounded): documents + envelope encryption + visibility wall.
 */
class DocumentEncryptionVisibilityTest extends EventModuleTestCase
{
    private Church $church;

    private DocumentRepository $documents;

    private DocumentVisibility $visibility;

    private DocumentStorage $storage;

    private DocumentEnvelopeEncryption $encryption;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['documents.disk' => 'local']);

        $this->church = Church::main();
        TenantContext::set($this->church);
        $this->documents = app(DocumentRepository::class);
        $this->visibility = app(DocumentVisibility::class);
        $this->storage = app(DocumentStorage::class);
        $this->encryption = app(DocumentEnvelopeEncryption::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    public function test_morph_map_keeps_contact_and_document_aliases(): void
    {
        $map = Relation::morphMap();

        $this->assertSame(Person::class, $map[Contact::CONTACTABLE_PERSON] ?? null);
        $this->assertSame(Residence::class, $map[Contact::CONTACTABLE_RESIDENCE] ?? null);
        $this->assertSame(Person::class, $map[Document::DOCUMENTABLE_PERSON] ?? null);
        $this->assertSame(Residence::class, $map[Document::DOCUMENTABLE_RESIDENCE] ?? null);
        $this->assertSame(Sacrament::class, $map[Document::DOCUMENTABLE_SACRAMENT] ?? null);
        $this->assertSame(HomeVisit::class, $map[Document::DOCUMENTABLE_VISIT] ?? null);
    }

    public function test_schema_documents_and_org_dek_column(): void
    {
        $this->assertTrue(Schema::hasTable('documents'));
        $this->assertTrue(Schema::hasColumn('documents', 'document_id'));
        $this->assertTrue(Schema::hasColumn('documents', 'is_sensitive'));
        $this->assertTrue(Schema::hasColumn('documents', 'encryption_key_ref'));
        $this->assertTrue(Schema::hasColumn('documents', 'visibility_layer'));
        $this->assertTrue(Schema::hasColumn('organizations', 'documents_dek_wrapped'));
        $this->assertTrue(Schema::hasColumn('sacraments', 'certificate_document_id'));
    }

    public function test_sensitive_document_stored_as_ciphertext_not_plaintext(): void
    {
        [$priest] = $this->seedChurchMember('priest', 'doc-priest-enc');
        $person = $this->makePerson('حسّاس');
        $plaintext = 'NATIONAL-ID-SCAN-PLAINTEXT-SECRET-999';

        $doc = $this->documents->store($plaintext, $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $person->person_id,
            'kind' => 'id_scan',
            'visibility_layer' => Document::LAYER_PASTORAL,
            'is_sensitive' => true,
        ]);

        $this->assertTrue($doc->is_sensitive);
        $this->assertNotNull($doc->encryption_key_ref);
        $this->assertTrue($this->storage->exists($doc->storage_ref));

        $raw = $this->storage->get($doc->storage_ref);
        $this->assertStringNotContainsString($plaintext, $raw);
        $this->assertNotSame($plaintext, $raw);

        // Hidden from JSON / API serialization.
        $json = $doc->toArray();
        $this->assertArrayNotHasKey('encryption_key_ref', $json);
        $this->assertArrayNotHasKey('storage_ref', $json);
        $this->assertFalse($doc->isReadableByAi());
    }

    public function test_authorized_role_decrypts_sensitive_document(): void
    {
        [$priest] = $this->seedChurchMember('priest', 'doc-priest-ok');
        $person = $this->makePerson('مصرّح');
        $plaintext = 'pastoral-attachment-body';

        $doc = $this->documents->store($plaintext, $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $person->person_id,
            'kind' => 'safeguarding_note_scan',
            'visibility_layer' => Document::LAYER_PASTORAL,
            'is_sensitive' => true,
        ]);

        $recovered = $this->documents->retrieve($doc, $priest);
        $this->assertSame($plaintext, $recovered);

        $ledger = AccessLedgerEntry::query()
            ->where('action', 'document.read_sensitive')
            ->where('subject_id', $doc->document_id)
            ->latest('access_ledger_id')
            ->first();

        $this->assertNotNull($ledger);
        $this->assertSame('ok', $ledger->context['outcome'] ?? null);
        $contextJson = json_encode($ledger->context);
        $this->assertStringNotContainsString('encryption_key_ref', (string) $contextJson);
        $this->assertStringNotContainsString($plaintext, (string) $contextJson);
        $org = Organization::main();
        $this->assertStringNotContainsString((string) $org->documents_dek_wrapped, (string) $contextJson);
    }

    public function test_decryption_fails_without_correct_organization_key(): void
    {
        [$priest] = $this->seedChurchMember('priest', 'doc-priest-fail');
        $person = $this->makePerson('فشل');
        $plaintext = 'must-not-decrypt-with-wrong-key';

        $doc = $this->documents->store($plaintext, $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $person->person_id,
            'kind' => 'id_scan',
            'visibility_layer' => Document::LAYER_PASTORAL,
            'is_sensitive' => true,
        ]);

        $org = $this->encryption->resolvePlacementOrganization($this->church);
        // Corrupt the wrapped DEK so unwrap yields null / wrong key material.
        $org->forceFill([
            'documents_dek_wrapped' => base64_encode(random_bytes(64)),
        ])->save();

        $this->expectException(RuntimeException::class);
        $this->documents->retrieve($doc->fresh(), $priest);
    }

    public function test_family_sees_custodial_but_not_pastoral_or_sensitive(): void
    {
        [$guardian, , $ward] = $this->seedGuardianWard();
        [$priest] = $this->seedChurchMember('priest', 'doc-vis-priest');

        $custodial = $this->documents->store('home-photo-bytes', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $ward->person_id,
            'kind' => 'home_photo',
            'visibility_layer' => Document::LAYER_CUSTODIAL,
            'is_sensitive' => false,
        ]);

        $pastoral = $this->documents->store('pastoral-doc-bytes', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $ward->person_id,
            'kind' => 'visit_attachment',
            'visibility_layer' => Document::LAYER_PASTORAL,
            'is_sensitive' => false,
        ]);

        $sensitive = $this->documents->store('SENSITIVE-BYTES', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $ward->person_id,
            'kind' => 'id_scan',
            'visibility_layer' => Document::LAYER_CUSTODIAL,
            'is_sensitive' => true,
        ]);

        $this->assertTrue($this->visibility->canView($guardian, $custodial));
        $this->assertFalse($this->visibility->canView($guardian, $pastoral));
        $this->assertFalse($this->visibility->canView($guardian, $sensitive));

        $visible = $this->visibility->visibleFor(
            $guardian,
            Document::DOCUMENTABLE_PERSON,
            (int) $ward->person_id
        );
        $this->assertCount(1, $visible);
        $this->assertSame($custodial->document_id, $visible->first()->document_id);
    }

    public function test_unrelated_servant_cannot_see_sensitive_or_pastoral_via_generic_permission(): void
    {
        [$priest] = $this->seedChurchMember('priest', 'doc-vis-priest2');
        [$servant] = $this->seedChurchMember('servant', 'doc-vis-servant');
        $person = $this->makePerson('غريب');

        $custodial = $this->documents->store('custodial-bytes', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $person->person_id,
            'kind' => 'home_photo',
            'visibility_layer' => Document::LAYER_CUSTODIAL,
            'is_sensitive' => false,
        ]);

        $pastoral = $this->documents->store('pastoral-bytes', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $person->person_id,
            'kind' => 'visit_attachment',
            'visibility_layer' => Document::LAYER_PASTORAL,
            'is_sensitive' => false,
        ]);

        $sensitive = $this->documents->store('SENSITIVE-BYTES', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $person->person_id,
            'kind' => 'id_scan',
            'visibility_layer' => Document::LAYER_CUSTODIAL,
            'is_sensitive' => true,
        ]);

        // The servant role template grants documents.view/upload but has no relationship
        // (family/uploader/priest/admin) to this unrelated person's documents.
        $this->assertTrue($this->visibility->canView($servant, $custodial));
        $this->assertFalse($this->visibility->canView($servant, $pastoral));
        $this->assertFalse($this->visibility->canView($servant, $sensitive));

        $visible = $this->visibility->visibleFor(
            $servant,
            Document::DOCUMENTABLE_PERSON,
            (int) $person->person_id
        );
        $this->assertCount(1, $visible);
        $this->assertSame($custodial->document_id, $visible->first()->document_id);
    }

    public function test_safeguarding_restricted_subject_hides_documents_from_generic_permission_holder(): void
    {
        [$priest] = $this->seedChurchMember('priest', 'doc-vis-priest3');
        [$servant] = $this->seedChurchMember('servant', 'doc-vis-servant2');
        [, , $ward] = $this->seedGuardianWard();

        Relationship::withoutTenancy()
            ->guardianOf()
            ->where('related_person_id', $ward->person_id)
            ->update(['guardian_visibility' => Relationship::VISIBILITY_RESTRICTED]);

        $custodial = $this->documents->store('restricted-custodial-bytes', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $ward->person_id,
            'kind' => 'home_photo',
            'visibility_layer' => Document::LAYER_CUSTODIAL,
            'is_sensitive' => false,
        ]);

        // Even a non-sensitive custodial document is hidden from the generic-permission
        // servant once the subject's guardian edge is safeguarding-restricted.
        $this->assertFalse($this->visibility->canView($servant, $custodial));
        $this->assertTrue($this->visibility->canView($priest, $custodial));
    }

    public function test_documentable_must_exist_in_app(): void
    {
        [$priest] = $this->seedChurchMember('priest', 'doc-orphan');

        $this->expectException(ValidationException::class);
        $this->documents->store('x', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => 9_999_999,
            'kind' => 'orphan',
            'visibility_layer' => Document::LAYER_CUSTODIAL,
            'is_sensitive' => false,
        ]);
    }

    public function test_sacrament_certificate_link_and_storage_scope(): void
    {
        [$priest] = $this->seedChurchMember('priest', 'doc-cert');
        $person = $this->makePerson('معمّد');
        $sacrament = app(SacramentRepository::class)->record([
            'church_id' => (int) $this->church->church_id,
            'person_id' => (int) $person->person_id,
            'type' => Sacrament::TYPE_BAPTISM,
            'date' => '2010-01-15',
            'date_precision' => Sacrament::PRECISION_DAY,
            'recorded_by' => (int) $priest->user_id,
        ]);

        $doc = $this->documents->store('certificate-pdf-bytes', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_SACRAMENT,
            'documentable_id' => (int) $sacrament->sacrament_id,
            'kind' => 'baptism_certificate',
            'visibility_layer' => Document::LAYER_SACRAMENTAL,
            'is_sensitive' => false,
            'link_as_sacrament_certificate' => true,
        ]);

        $sacrament->refresh();
        $this->assertSame((int) $doc->document_id, (int) $sacrament->certificate_document_id);

        $placement = $this->storage->placementOrganization($this->church);
        $this->assertTrue($this->storage->isUnderPlacement($doc->storage_ref, $placement));
    }

    /**
     * Isolated-diocese storage prefix — verify fully once Slice 12 lands.
     * Placement org is the diocese ancestor; db_isolated stays false here so we
     * do not open a real isolated tenant connection (Slice 12 provisioning).
     *
     * @group slice12-verify
     */
    public function test_isolated_diocese_document_uses_placement_org_storage_scope(): void
    {
        $church = $this->createChurch(['slug' => 'doc-isol-church', 'name' => 'Doc Isol Church']);
        $churchOrg = Organization::query()->findOrFail($church->organization_id);

        $diocese = Organization::query()->create([
            'type' => Organization::TYPE_DIOCESE,
            'subdomain' => 'doc-isol-diocese-'.uniqid(),
            'name' => 'Doc Isol Diocese',
            'status' => 'active',
            // Path-scoping only until Slice 12 wires isolated DB + storage root.
            'placement_policy' => Organization::PLACEMENT_SHARED,
            'db_isolated' => false,
        ]);

        $churchOrg->forceFill(['parent_id' => $diocese->organization_id])->save();
        $church->refresh();

        TenantContext::set($church);
        [$priest] = $this->seedChurchMemberFor($church, 'priest', 'doc-isol-priest');
        $person = Person::withoutTenancy()->create([
            'church_id' => $church->church_id,
            'first_name' => 'معزول',
            'second_name' => 'أ',
            'third_name' => 'ب',
            'date_of_birth' => '1990-01-01',
            'is_minor' => false,
        ]);

        $doc = $this->documents->store('isolated-bytes', $priest, [
            'documentable_type' => Document::DOCUMENTABLE_PERSON,
            'documentable_id' => (int) $person->person_id,
            'kind' => 'deed',
            'visibility_layer' => Document::LAYER_CUSTODIAL,
            'is_sensitive' => false,
        ], $church);

        // resolvePlacementOrganization walks to type=diocese ancestor.
        $this->assertTrue($this->storage->isUnderPlacement($doc->storage_ref, $diocese));
        $this->assertStringContainsString('/'.(int) $diocese->organization_id.'/', $doc->storage_ref);
        $this->assertFalse($this->storage->isUnderPlacement($doc->storage_ref, Organization::main()));
    }

    public function test_organization_hides_dek_from_json(): void
    {
        $org = Organization::main();
        $this->encryption->ensureDataKey($org);
        $org->refresh();

        $this->assertNotEmpty($org->documents_dek_wrapped);
        $this->assertArrayNotHasKey('documents_dek_wrapped', $org->toArray());
        $this->assertArrayNotHasKey('db_password_encrypted', $org->toArray());
    }

    /** @return array{0: User} */
    private function seedChurchMember(string $templateSlug, string $emailPrefix): array
    {
        return $this->seedChurchMemberFor($this->church, $templateSlug, $emailPrefix);
    }

    /** @return array{0: User} */
    private function seedChurchMemberFor(Church $church, string $templateSlug, string $emailPrefix): array
    {
        $roles = app(RoleTemplateService::class)->cloneTemplatesIntoChurch($church);
        $user = $this->createUser(['email' => $emailPrefix.'@example.com']);

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

        if ($templateSlug === 'priest') {
            Priest::query()->create([
                'church_id' => $church->church_id,
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
            'email' => 'doc-guardian-'.uniqid().'@example.com',
            'date_of_birth' => '1980-01-01',
        ]);
        $guardianPerson = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'وصي',
            'second_name' => 'مستند',
            'third_name' => 'أ',
            'date_of_birth' => '1980-01-01',
            'is_minor' => false,
        ]);
        $guardianUser->forceFill(['person_id' => $guardianPerson->person_id])->save();

        $ward = Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => 'قاصر',
            'second_name' => 'مستند',
            'third_name' => 'ب',
            'date_of_birth' => '2015-01-01',
            'is_minor' => true,
        ]);

        $verifier = $this->createUser(['email' => 'doc-verifier-'.uniqid().'@example.com']);
        $edge = app(GuardianshipService::class)->linkGuardian(
            $guardianPerson,
            $ward,
            $verifier,
            $this->church
        );

        return [$guardianUser->fresh(), $guardianPerson, $ward, $edge];
    }

    private function makePerson(string $first): Person
    {
        return Person::withoutTenancy()->create([
            'church_id' => $this->church->church_id,
            'first_name' => $first,
            'second_name' => 'مستند',
            'third_name' => 'ت',
            'date_of_birth' => '1995-01-01',
            'is_minor' => false,
        ]);
    }
}
