<?php

namespace App\Services\Documents;

use App\Models\Church;
use App\Models\Document;
use App\Models\User;
use App\Services\AccessLedger\AccessLedgerRepository;
use App\Services\AuditLogService;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Upload / retrieve documents. Sensitive path encrypts at rest; reads of
 * sensitive docs append to the access ledger without key material in context.
 */
final class DocumentRepository
{
    public function __construct(
        private DocumentEnvelopeEncryption $encryption,
        private DocumentStorage $storage,
        private DocumentVisibility $visibility,
        private AccessLedgerRepository $accessLedger,
    ) {}

    /**
     * @param  array{
     *     documentable_type: string,
     *     documentable_id: int,
     *     kind: string,
     *     visibility_layer: string,
     *     is_sensitive?: bool,
     *     link_as_sacrament_certificate?: bool
     * }  $meta
     */
    public function store(
        UploadedFile|string $contents,
        User $uploader,
        array $meta,
        ?Church $church = null,
    ): Document {
        $church = $church ?? TenantContext::current();
        if (! $church) {
            throw ValidationException::withMessages([
                'church' => [__('documents.errors.no_church')],
            ]);
        }

        $type = (string) $meta['documentable_type'];
        $id = (int) $meta['documentable_id'];
        $layer = (string) $meta['visibility_layer'];
        $kind = trim((string) $meta['kind']);
        $sensitive = (bool) ($meta['is_sensitive'] ?? false);

        if ($kind === '') {
            throw ValidationException::withMessages([
                'kind' => [__('documents.errors.kind_required')],
            ]);
        }

        if (! in_array($layer, Document::LAYERS, true)) {
            throw ValidationException::withMessages([
                'visibility_layer' => [__('documents.errors.invalid_layer')],
            ]);
        }

        $this->visibility->assertDocumentableExists($type, $id);

        $bytes = $contents instanceof UploadedFile
            ? (string) file_get_contents($contents->getRealPath())
            : (string) $contents;

        $extension = $contents instanceof UploadedFile
            ? ($contents->getClientOriginalExtension() ?: 'bin')
            : 'bin';

        $storageRef = $this->storage->makeStorageRef($church, $extension);
        $keyRef = null;

        if ($sensitive) {
            $encrypted = $this->encryption->encrypt($bytes, $church);
            $this->storage->put($storageRef, $encrypted['ciphertext']);
            $keyRef = $encrypted['encryption_key_ref'];
        } else {
            $this->storage->put($storageRef, $bytes);
        }

        $document = DB::transaction(function () use (
            $church,
            $type,
            $id,
            $kind,
            $storageRef,
            $sensitive,
            $keyRef,
            $layer,
            $uploader,
            $meta
        ) {
            $doc = new Document([
                'documentable_type' => $type,
                'documentable_id' => $id,
                'kind' => $kind,
                'storage_ref' => $storageRef,
                'is_sensitive' => $sensitive,
                'encryption_key_ref' => $keyRef,
                'visibility_layer' => $layer,
                'uploaded_by' => $uploader->user_id,
                'uploaded_at' => now(),
                'created_at' => now(),
            ]);
            $doc->church_id = $church->church_id;
            $doc->save();

            if (
                ($meta['link_as_sacrament_certificate'] ?? false)
                && $type === Document::DOCUMENTABLE_SACRAMENT
            ) {
                // Sacraments are append-only via the model; certificate FK is a typed
                // link filled once (query builder — does not fire updating hooks).
                DB::table('sacraments')
                    ->where('sacrament_id', $id)
                    ->whereNull('certificate_document_id')
                    ->update(['certificate_document_id' => $doc->document_id]);
            }

            return $doc;
        });

        AuditLogService::recordEvent('documents.uploaded', [
            'document_id' => $document->document_id,
            'documentable_type' => $type,
            'documentable_id' => $id,
            'visibility_layer' => $layer,
            'is_sensitive' => $sensitive,
            // Never include encryption_key_ref or key material.
        ]);

        return $document;
    }

    /**
     * Retrieve plaintext bytes when the viewer is allowed.
     *
     * @throws ValidationException|RuntimeException
     */
    public function retrieve(Document $document, User $viewer): string
    {
        if (! $this->visibility->canView($viewer, $document)) {
            throw ValidationException::withMessages([
                'document' => [__('documents.errors.forbidden')],
            ]);
        }

        $raw = $this->storage->get($document->storage_ref);

        if (! $document->is_sensitive) {
            return $raw;
        }

        $org = $this->encryption->organizationFromKeyRef((string) ($document->encryption_key_ref ?? ''));
        $outcome = 'ok';

        try {
            $plain = $this->encryption->decrypt(
                $raw,
                (string) $document->encryption_key_ref,
                $org
            );
        } catch (RuntimeException $e) {
            $outcome = 'fail';
            $this->appendSensitiveReadLedger($document, $viewer, $outcome);
            throw $e;
        }

        $this->appendSensitiveReadLedger($document, $viewer, $outcome);

        return $plain;
    }

    private function appendSensitiveReadLedger(Document $document, User $viewer, string $outcome): void
    {
        $this->accessLedger->append([
            'actor_type' => 'user',
            'actor_id' => (int) $viewer->user_id,
            'action' => 'document.read_sensitive',
            'subject_type' => 'document',
            'subject_id' => (int) $document->document_id,
            'church_id' => (int) $document->church_id,
            'organization_id' => $document->church?->organization_id,
            'context' => [
                'purpose' => 'decrypt',
                'outcome' => $outcome,
                // Never: encryption_key_ref, DEK, master key, ciphertext, plaintext.
            ],
        ]);
    }
}
