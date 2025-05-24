@extends('layouts.app')

@section('title', 'خطأ في التسجيل')

@section('content')
<div class="container mt-5 text-center">
    <h2 class="text-danger">حدث خطأ في التسجيل</h2>
    <p>{{ $message }}</p>
    @if(config('app.debug'))
        <small class="text-muted">تفاصيل الخطأ: {{ $details }}</small>
    @endif
    <a href="{{ route('register') }}" class="btn btn-primary mt-3">العودة إلى التسجيل</a>
</div>
@endsection
