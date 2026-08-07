@extends('layouts.app')
@section('title', 'Notifications')
@section('page-header')
<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">My Account</div><h2 class="page-title">Notifications</h2></div><div class="col-auto"><form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-primary">Mark all as read</button></form></div></div></div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<div class="card"><div class="list-group list-group-flush">@forelse($notifications as $notification)<div class="list-group-item {{ $notification->read_at ? '' : 'bg-blue-lt' }}"><div class="row align-items-center"><div class="col-auto"><span class="status-dot {{ $notification->read_at ? '' : 'status-dot-animated bg-red' }}"></span></div><div class="col"><div class="fw-bold">{{ $notification->title }}</div><div class="text-secondary">{{ $notification->message }}</div><div class="small text-secondary mt-1">{{ $notification->created_at?->diffForHumans() }}</div></div><div class="col-auto">@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification) }}">@csrf<button class="btn btn-sm btn-outline-primary">Mark read</button></form>@endif</div></div></div>@empty<div class="text-center text-secondary py-5">No notifications.</div>@endforelse</div><div class="card-footer">{{ $notifications->links() }}</div></div>
@endsection
