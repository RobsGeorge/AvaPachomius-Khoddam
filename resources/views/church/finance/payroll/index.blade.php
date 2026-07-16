@extends('layouts.app')
@section('title', __('finance.payroll_title'))
@section('content')
<div class="container py-4" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('finance.payroll_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('finance.payroll_intro') }}</p>
        </div>
        <a href="{{ route('church.finance.payroll.create') }}" class="btn btn-primary">{{ __('finance.new_run') }}</a>
    </div>
    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('finance.period_start') }}</th>
                        <th>{{ __('finance.period_end') }}</th>
                        <th>{{ __('finance.currency') }}</th>
                        <th>{{ __('finance.status') }}</th>
                        <th>{{ __('finance.lines') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($runs as $run)
                        <tr>
                            <td>{{ $run->period_start?->format('Y-m-d') }}</td>
                            <td>{{ $run->period_end?->format('Y-m-d') }}</td>
                            <td>{{ $run->currency }}</td>
                            <td>{{ __('finance.status_'.$run->status) }}</td>
                            <td>{{ $run->lines_count }}</td>
                            <td class="text-end">
                                <a href="{{ route('church.finance.payroll.show', $run) }}" class="btn btn-sm btn-outline-primary">{{ __('finance.lines') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted-theme py-4">{{ __('finance.no_runs') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $runs->links() }}</div>
</div>
@endsection
