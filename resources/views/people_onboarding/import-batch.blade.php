@extends('layouts.app')

@section('title', __('people_onboarding.import_batch_title', ['id' => $batch->people_import_batch_id]))

@section('content')
<div class="container py-4 animate-in">
    <h1 class="page-title mb-2">{{ __('people_onboarding.import_batch_title', ['id' => $batch->people_import_batch_id]) }}</h1>
    <p class="text-muted-theme">{{ $batch->original_filename }} · {{ $batch->status }}</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($batch->status !== 'committed')
        <form method="POST" action="{{ route('people.import.commit', $batch) }}" class="mb-3">
            @csrf
            <button class="btn btn-primary" type="submit">{{ __('people_onboarding.import_commit') }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('people.import.bulk-invite', $batch) }}" class="mb-3">
            @csrf
            <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="send_email" value="1" id="send_email" checked>
                    <label class="form-check-label" for="send_email">{{ __('people_onboarding.bulk_invite_email') }}</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="send_whatsapp" value="1" id="send_whatsapp">
                    <label class="form-check-label" for="send_whatsapp">{{ __('people_onboarding.bulk_invite_whatsapp') }}</label>
                </div>
                <button class="btn btn-primary" type="submit">{{ __('people_onboarding.bulk_invite') }}</button>
            </div>
            <div class="table-responsive app-card card shadow-sm">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th></th>
                            <th>#</th>
                            <th>{{ __('people_onboarding.col_name') }}</th>
                            <th>{{ __('people_onboarding.col_email') }}</th>
                            <th>{{ __('people_onboarding.role') }}</th>
                            <th>{{ __('people_onboarding.col_status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($batch->rows as $row)
                            <tr>
                                <td>
                                    @if($row->person_id)
                                        <input type="checkbox" name="row_ids[]" value="{{ $row->people_import_row_id }}" @checked($row->invite_selected || $row->invite_eligible)>
                                    @endif
                                </td>
                                <td>{{ $row->row_number }}</td>
                                <td>{{ trim(($row->raw['first_name'] ?? '').' '.($row->raw['second_name'] ?? '')) }}</td>
                                <td>{{ $row->raw['email'] ?? '' }}</td>
                                <td>{{ $row->role_slug }}</td>
                                <td>{{ $row->match_action }}</td>
                                <td class="text-danger small">{{ $row->error_message }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </form>
    @endif

    @if($batch->status !== 'committed')
        <div class="table-responsive app-card card shadow-sm">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('people_onboarding.col_name') }}</th>
                        <th>{{ __('people_onboarding.col_email') }}</th>
                        <th>{{ __('people_onboarding.role') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($batch->rows as $row)
                        <tr>
                            <td>{{ $row->row_number }}</td>
                            <td>{{ trim(($row->raw['first_name'] ?? '').' '.($row->raw['second_name'] ?? '')) }}</td>
                            <td>{{ $row->raw['email'] ?? '' }}</td>
                            <td>{{ $row->role_slug }}</td>
                            <td class="text-danger small">{{ $row->error_message }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
@endsection
