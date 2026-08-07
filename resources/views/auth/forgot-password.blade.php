@extends('layouts.app')
@section('title', 'Forgot password')
@section('content')
<div class="row justify-content-center"><div class="col-md-5"><div class="card mt-5"><div class="card-body p-4"><h2>Forgot password?</h2><p class="text-secondary">Enter your email and we will send a reset link.</p>
@if(session('success'))
<div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger">{{ session('error') }}</div>
@endif
<form method="POST" action="{{ route('password.email') }}">@csrf<input class="form-control mb-3" type="email" name="email" required><button class="btn btn-primary w-100">Send reset link</button><a class="btn btn-link w-100" href="{{ route('login') }}">Back to sign in</a></form></div></div></div></div>
@endsection
