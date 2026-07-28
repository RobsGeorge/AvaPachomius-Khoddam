<div class="card shadow-sm mb-3 border-0 bg-light">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <span class="fw-semibold small">
                <i class="bi bi-hdd-stack text-primary"></i>
                {{ __('curriculum.storage_usage_title') }}
            </span>
            <span class="badge {{ ($storagePercent ?? 0) >= 90 ? 'bg-danger' : (($storagePercent ?? 0) >= 70 ? 'bg-warning text-dark' : 'bg-secondary') }}">
                {{ number_format($storagePercent ?? 0, 1) }}%
            </span>
        </div>
        <div class="progress mb-2" style="height:8px;" role="progressbar"
             aria-valuenow="{{ (int) ($storagePercent ?? 0) }}" aria-valuemin="0" aria-valuemax="100"
             aria-label="{{ __('curriculum.storage_usage_title') }}">
            <div class="progress-bar {{ ($storagePercent ?? 0) >= 90 ? 'bg-danger' : 'bg-primary' }}"
                 style="width: {{ min(100, (float) ($storagePercent ?? 0)) }}%"></div>
        </div>
        <div class="small text-muted">
            {{ __('curriculum.storage_usage_summary', [
                'used' => \App\Services\StorageFormat::bytes($storageUsed ?? 0),
                'quota' => \App\Services\StorageFormat::bytes($storageQuota ?? 0),
                'remaining' => \App\Services\StorageFormat::bytes($storageRemaining ?? 0),
            ]) }}
        </div>
    </div>
</div>
