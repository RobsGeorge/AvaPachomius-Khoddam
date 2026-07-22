@extends('layouts.app')
@section('title', __('finance.new_money_in'))
@section('content')
<div class="container py-4" style="max-width:640px;">
    <h1 class="page-title mb-3">{{ __('finance.new_money_in') }}</h1>
    @include('church.finance.money-in._form', [
        'action' => route('church.finance.money-in.store'),
        'entry' => null,
        'method' => 'POST',
    ])
</div>
@endsection
