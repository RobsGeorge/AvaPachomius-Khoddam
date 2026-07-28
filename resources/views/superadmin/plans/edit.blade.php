@extends('layouts.app')

@section('title', __('billing.edit_plan'))

@section('content')
@include('superadmin.plans._form', ['plan' => $plan, 'features' => $features, 'catalog' => $catalog, 'entitlementMap' => $entitlementMap])
@endsection
