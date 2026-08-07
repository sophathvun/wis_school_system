@extends('layouts.app')
@section('title', 'Feedback')
@section('content')
<div class="page-header"><div class="row align-items-center"><div class="col"><h2>Feedback</h2><div class="text-secondary">Send feedback to the system administrator.</div></div></div></div>
<div class="card"><div class="card-body">@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif@if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif<form method="POST" action="{{ route('feedback.save') }}">@csrf<label class="form-label">Subject</label><input class="form-control mb-3" name="subject" value="{{ old('subject') }}" required><label class="form-label">Message</label><textarea class="form-control mb-3" name="message" rows="6" required>{{ old('message') }}</textarea><button class="btn btn-primary">Submit Feedback</button></form></div></div>
@endsection
