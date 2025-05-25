@extends('layouts.app')

@section('content')
<div class="container">


    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif


        <div class="mb-3">
            <label for="email" class="form-label">سيتم ارسال لينك لتغيير كلمة السر على البريد الالكتروني الخاص بك</label>
        </br>
            <label for="email" class="form-label">لو فيه مشكلة، ابعت لروبير على طول :D</label>
        </div>

</div>
@endsection
