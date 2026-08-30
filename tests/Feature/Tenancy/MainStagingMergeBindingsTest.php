<?php

namespace Tests\Feature\Tenancy;

use App\Contracts\TenantSecretStore;
use App\Models\Contact;
use App\Models\Document;
use App\Models\HomeVisit;
use App\Models\Person;
use App\Models\Residence;
use App\Models\Sacrament;
use App\Observability\Contracts\ErrorSink;
use App\Observability\ObservabilityRecorder;
use App\Services\Tenancy\EncryptedConfigTenantSecretStore;
use Illuminate\Database\Eloquent\Relations\Relation;
use Tests\Support\EventModuleTestCase;

/**
 * Guards the main→staging merge: keep staging document morph aliases and
 * main's observability / tenant-secret container bindings together.
 */
class MainStagingMergeBindingsTest extends EventModuleTestCase
{
    public function test_container_keeps_observability_and_tenant_secret_bindings(): void
    {
        $this->assertInstanceOf(ErrorSink::class, app(ErrorSink::class));
        $this->assertInstanceOf(ObservabilityRecorder::class, app(ObservabilityRecorder::class));
        $this->assertInstanceOf(EncryptedConfigTenantSecretStore::class, app(TenantSecretStore::class));
    }

    public function test_morph_map_registers_document_aliases_alongside_contacts(): void
    {
        $map = Relation::morphMap();

        $this->assertSame(Person::class, $map[Contact::CONTACTABLE_PERSON] ?? null);
        $this->assertSame(Residence::class, $map[Contact::CONTACTABLE_RESIDENCE] ?? null);
        $this->assertSame(Sacrament::class, $map[Document::DOCUMENTABLE_SACRAMENT] ?? null);
        $this->assertSame(HomeVisit::class, $map[Document::DOCUMENTABLE_VISIT] ?? null);
    }
}
