@extends('layouts.app')
@section('title', __('finance.edit_money_in'))
@section('content')
@php use App\Support\Money; @endphp
<div class="container py-4" style="max-width:640px;">
    <h1 class="page-title mb-3">{{ __('finance.edit_money_in') }}</h1>
    @include('church.finance.money-in._form', [
        'action' => route('church.finance.money-in.update', $entry),
        'entry' => $entry,
        'method' => 'PUT',
    ])
</div>
@endsection
