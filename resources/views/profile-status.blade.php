@extends('layouts.app')
@section('title', 'Account Status')
@section('content')
<div class="page-header"><div class="row align-items-center"><div class="col"><h2>Account Status</h2><div class="text-secondary">Your current account access status.</div></div></div></div>
<div class="card"><div class="card-body"><div class="d-flex align-items-center gap-3"><span class="avatar bg-{{ auth()->user()->status ? 'success-lt' : 'secondary-lt' }}"><i class="ti ti-user-check"></i></span><div><h3 class="mb-1">{{ auth()->user()->status ? 'Active' : 'Inactive' }}</h3><div class="text-secondary">{{ auth()->user()->status ? 'You can access the system according to your assigned permissions.' : 'Your account is inactive. Please contact an administrator.' }}</div></div></div></div></div>
@endsection
