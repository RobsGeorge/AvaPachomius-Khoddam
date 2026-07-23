@extends('layouts.app')
@section('title', __('finance.money_in_title'))
@section('content')
@php use App\Support\Money; @endphp
<div class="container py-4" style="max-width:960px;">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
            <h1 class="page-title mb-1">{{ __('finance.money_in_title') }}</h1>
            <p class="text-muted-theme mb-0">{{ __('finance.money_in_intro') }}</p>
        </div>
        <a href="{{ route('church.finance.money-in.create') }}" class="btn btn-primary">{{ __('finance.new_money_in') }}</a>
    </div>
    <div class="app-card card shadow-sm">
        <div class="table-responsive">
            <table class="table mb-0 align-middle">
                <thead>
                    <tr>
                        <th>{{ __('finance.received_at') }}</th>
                        <th>{{ __('finance.source') }}</th>
                        <th>{{ __('finance.category') }}</th>
                        <th>{{ __('finance.amount') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($entries as $entry)
                        <tr>
                            <td>{{ $entry->received_at?->format('Y-m-d') }}</td>
                            <td>{{ $entry->source }}</td>
                            <td>{{ $entry->category }}</td>
                            <td>{{ Money::fromMinor((int) $entry->amount_minor) }} {{ $entry->currency }}</td>
                            <td class="text-end">
                                <a href="{{ route('church.finance.money-in.edit', $entry) }}" class="btn btn-sm btn-outline-primary">{{ __('finance.edit_money_in') }}</a>
                                <form method="POST" action="{{ route('church.finance.money-in.destroy', $entry) }}" class="d-inline" data-confirm="{{ __('finance.confirm_delete') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger">{{ __('finance.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted-theme py-4">{{ __('finance.no_money_in') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-3">{{ $entries->links() }}</div>
</div>
@endsection
