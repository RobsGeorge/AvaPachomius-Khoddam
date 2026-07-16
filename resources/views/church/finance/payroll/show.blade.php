@extends('layouts.app')
@section('title', __('finance.payroll_title'))
@section('content')
@php use App\Support\Money; @endphp
<div class="container py-4" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('finance.payroll_title') }}</h1>
            <p class="text-muted-theme mb-0">
                {{ $run->period_start?->format('Y-m-d') }} → {{ $run->period_end?->format('Y-m-d') }}
                · {{ $run->currency }}
                · {{ __('finance.status_'.$run->status) }}
            </p>
        </div>
        <a href="{{ route('church.finance.payroll.index') }}" class="btn btn-outline-secondary">{{ __('finance.back') }}</a>
    </div>

    @if($run->isDraft())
        <div class="app-card card shadow-sm p-4 mb-4">
            <h2 class="h5 mb-3">{{ __('finance.add_line') }}</h2>
            <form method="POST" action="{{ route('church.finance.payroll.lines.store', $run) }}" class="row g-3">
                @csrf
                <div class="col-md-4">
                    <label class="form-label" for="email">{{ __('finance.email') }}</label>
                    <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}" required>
                    @error('email')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="gross">{{ __('finance.gross') }}</label>
                    <input type="text" name="gross" id="gross" class="form-control" inputmode="decimal" value="{{ old('gross') }}" required>
                    @error('gross')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-3">
                    <label class="form-label" for="deductions">{{ __('finance.deductions') }}</label>
                    <input type="text" name="deductions" id="deductions" class="form-control" inputmode="decimal" value="{{ old('deductions', '0') }}">
                    @error('deductions')<div class="text-danger small">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">{{ __('finance.save') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('finance.email') }}</th>
                        <th>{{ __('finance.gross') }}</th>
                        <th>{{ __('finance.deductions') }}</th>
                        <th>{{ __('finance.net') }}</th>
                        <th>{{ __('finance.fx_rate') }}</th>
                        @if($run->isDraft())<th></th>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse($run->lines as $line)
                        <tr>
                            <td>{{ $line->user?->email }}</td>
                            <td>{{ Money::fromMinor((int) $line->gross_minor) }} {{ $line->currency }}</td>
                            <td>{{ Money::fromMinor((int) $line->deductions_minor) }}</td>
                            <td>{{ Money::fromMinor((int) $line->net_minor) }}</td>
                            <td>{{ $line->fx_rate }}</td>
                            @if($run->isDraft())
                                <td class="text-end">
                                    <form method="POST" action="{{ route('church.finance.payroll.lines.destroy', [$run, $line]) }}" onsubmit="return confirm(@json(__('finance.confirm_delete')))">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('finance.delete_line') }}</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $run->isDraft() ? 6 : 5 }}" class="text-center text-muted-theme py-4">{{ __('finance.no_lines') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($run->isDraft())
        <div class="d-flex flex-wrap gap-2 mt-3">
            <form method="POST" action="{{ route('church.finance.payroll.finalize', $run) }}">
                @csrf
                <button type="submit" class="btn btn-primary">{{ __('finance.finalize') }}</button>
            </form>
            <form method="POST" action="{{ route('church.finance.payroll.destroy', $run) }}" onsubmit="return confirm(@json(__('finance.confirm_delete')))">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">{{ __('finance.delete_run') }}</button>
            </form>
        </div>
    @endif
</div>
@endsection
