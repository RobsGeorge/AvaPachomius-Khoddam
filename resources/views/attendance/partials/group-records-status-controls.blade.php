@php
    $showPermissionReasonColumn = $showPermissionReasonColumn ?? false;
@endphp
<select class="status-select form-select form-select-sm"
        data-attendance-id="{{ $record->attendance_id }}"
        data-current-status="{{ $record->status }}"
        onchange="updateStatus(this)">
    <option value="Present" {{ $record->status === 'Present' ? 'selected' : '' }}>{{ __('pages.present') }}</option>
    <option value="Absent" {{ $record->status === 'Absent' ? 'selected' : '' }}>{{ __('pages.absent') }}</option>
    <option value="Late" {{ $record->status === 'Late' ? 'selected' : '' }}>{{ __('pages.late') }}</option>
    <option value="Permission" {{ $record->status === 'Permission' ? 'selected' : '' }}>{{ __('pages.permission') }}</option>
</select>
@if(! $showPermissionReasonColumn)
    <div id="permission-reason-{{ $record->attendance_id }}" class="mt-1 {{ $record->status === 'Permission' ? '' : 'd-none' }}">
        <input type="text"
               class="permission-reason form-control form-control-sm"
               data-attendance-id="{{ $record->attendance_id }}"
               placeholder="{{ __('pages.permission_reason') }}"
               value="{{ $record->permission_reason }}"
               onchange="updatePermissionReason(this)">
    </div>
@endif
