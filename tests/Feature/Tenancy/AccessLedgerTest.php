<?php

namespace Tests\Feature\Tenancy;

use App\Models\AccessLedgerEntry;
use App\Services\AccessLedger\AccessLedgerRepository;
use App\Support\AccessLedger\LedgerHashChain;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\EventModuleTestCase;

class AccessLedgerTest extends EventModuleTestCase
{
    public function test_append_inserts_chained_row(): void
    {
        $repo = app(AccessLedgerRepository::class);

        $first = $repo->append([
            'actor_type' => AccessLedgerEntry::ACTOR_SYSTEM,
            'action' => AccessLedgerEntry::ACTION_WRITE,
            'subject_type' => 'family',
            'subject_id' => 1,
            'church_id' => 1,
            'organization_id' => 1,
            'context' => ['route' => 'test', 'secret_payload' => 'MUST_NOT_PERSIST'],
        ]);

        $this->assertNotNull($first->access_ledger_id);
        $this->assertSame(LedgerHashChain::GENESIS_HASH, $first->prev_hash);
        $this->assertNotEmpty($first->row_hash);
        $this->assertSame(['route' => 'test'], $first->context);
        $this->assertArrayNotHasKey('secret_payload', $first->context ?? []);

        $second = $repo->append([
            'actor_type' => AccessLedgerEntry::ACTOR_STAFF,
            'actor_id' => 9,
            'action' => AccessLedgerEntry::ACTION_READ,
            'subject_type' => 'family',
            'subject_id' => 2,
        ]);

        $this->assertSame($first->row_hash, $second->prev_hash);
        $this->assertTrue($repo->verifyChain()['ok']);
    }

    public function test_model_update_and_delete_throw(): void
    {
        $repo = app(AccessLedgerRepository::class);
        $entry = $repo->append([
            'actor_type' => AccessLedgerEntry::ACTOR_USER,
            'actor_id' => 1,
            'action' => AccessLedgerEntry::ACTION_EXPORT,
        ]);

        $this->expectException(LogicException::class);
        $entry->update(['action' => AccessLedgerEntry::ACTION_READ]);
    }

    public function test_model_delete_throws(): void
    {
        $repo = app(AccessLedgerRepository::class);
        $entry = $repo->append([
            'actor_type' => AccessLedgerEntry::ACTOR_USER,
            'actor_id' => 1,
            'action' => AccessLedgerEntry::ACTION_MERGE,
        ]);

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_repository_update_and_delete_throw(): void
    {
        $repo = app(AccessLedgerRepository::class);
        $entry = $repo->append([
            'actor_type' => AccessLedgerEntry::ACTOR_SYSTEM,
            'action' => AccessLedgerEntry::ACTION_WRITE,
        ]);

        try {
            $repo->update($entry, ['action' => 'x']);
            $this->fail('Expected update to throw');
        } catch (LogicException $e) {
            $this->assertStringContainsString('refuses updates', $e->getMessage());
        }

        try {
            $repo->delete($entry);
            $this->fail('Expected delete to throw');
        } catch (LogicException $e) {
            $this->assertStringContainsString('refuses deletes', $e->getMessage());
        }
    }

    public function test_ledger_verify_passes_on_clean_chain(): void
    {
        $repo = app(AccessLedgerRepository::class);
        $repo->append(['actor_type' => AccessLedgerEntry::ACTOR_SYSTEM, 'action' => AccessLedgerEntry::ACTION_WRITE]);
        $repo->append(['actor_type' => AccessLedgerEntry::ACTOR_SYSTEM, 'action' => AccessLedgerEntry::ACTION_READ]);

        $this->assertTrue($repo->verifyChain()['ok']);
        $this->artisan('ledger:verify')->assertSuccessful();
    }

    public function test_ledger_verify_pinpoints_middle_row_tamper(): void
    {
        $repo = app(AccessLedgerRepository::class);
        $a = $repo->append(['actor_type' => AccessLedgerEntry::ACTOR_SYSTEM, 'action' => AccessLedgerEntry::ACTION_WRITE, 'subject_id' => 1]);
        $b = $repo->append(['actor_type' => AccessLedgerEntry::ACTOR_SYSTEM, 'action' => AccessLedgerEntry::ACTION_READ, 'subject_id' => 2]);
        $c = $repo->append(['actor_type' => AccessLedgerEntry::ACTOR_SYSTEM, 'action' => AccessLedgerEntry::ACTION_EXPORT, 'subject_id' => 3]);

        $this->assertTrue($repo->verifyChain()['ok']);

        // Bypass Eloquent guards — mutate the MIDDLE row only.
        DB::table('access_ledger')
            ->where('access_ledger_id', $b->access_ledger_id)
            ->update(['action' => AccessLedgerEntry::ACTION_RECOVERY]);

        $result = $repo->verifyChain();
        $this->assertFalse($result['ok']);
        $this->assertSame((int) $b->access_ledger_id, $result['broken_at_id']);
        $this->assertNotSame((int) $c->access_ledger_id, $result['broken_at_id']);
        $this->assertNotSame((int) $a->access_ledger_id, $result['broken_at_id']);

        $this->artisan('ledger:verify')
            ->assertFailed()
            ->expectsOutputToContain((string) $b->access_ledger_id);
    }
}