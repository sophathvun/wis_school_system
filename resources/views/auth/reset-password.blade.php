@extends('layouts.app')
@section('title', 'Reset password')
@section('content')
<div class="row justify-content-center"><div class="col-md-5"><div class="card mt-5"><div class="card-body p-4"><h2>Reset password</h2>@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif<form method="POST" action="{{ route('password.update') }}">@csrf<input type="hidden" name="token" value="{{ $token }}"><label class="form-label">Email</label><input class="form-control mb-3" type="email" name="email" value="{{ old('email', $email) }}" required><label class="form-label">New password</label><input class="form-control mb-3" type="password" name="password" minlength="8" required><label class="form-label">Confirm password</label><input class="form-control mb-3" type="password" name="password_confirmation" minlength="8" required><button class="btn btn-primary w-100">Reset password</button></form></div></div></div></div>
@endsection
