@extends('layouts.app')

@section('title', __('billing.create_plan'))

@section('content')
@include('superadmin.plans._form', ['features' => $features, 'catalog' => $catalog])
@endsection
