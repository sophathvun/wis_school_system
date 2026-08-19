@extends('layouts.app')
@section('title', 'Notifications')
@section('page-header')
<div class="container-fluid"><div class="row g-2 align-items-center"><div class="col"><div class="page-pretitle">My Account</div><h2 class="page-title">Notifications</h2></div><div class="col-auto"><form method="POST" action="{{ route('notifications.read-all') }}">@csrf<button class="btn btn-outline-primary">Mark all as read</button></form></div></div></div>
@endsection
@section('content')
@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
<style>.notification-message-content{line-height:1.6}.notification-message-content img{max-width:100%;height:auto;border-radius:10px;margin:.5rem 0;box-shadow:0 5px 18px rgba(31,41,55,.12)}</style>
<div class="card"><div class="list-group list-group-flush">@forelse($notifications as $notification)<div class="list-group-item {{ $notification->read_at ? '' : 'bg-blue-lt' }}"><div class="row align-items-center"><div class="col-auto"><span class="status-dot {{ $notification->read_at ? '' : 'status-dot-animated bg-red' }}"></span></div><div class="col"><div class="fw-bold">{{ $notification->title }}</div><div class="notification-message-content text-secondary">{!! $notification->message !!}</div>@if($notification->action_url)<a class="btn btn-sm btn-outline-primary mt-2" href="{{ $notification->action_url }}" target="_blank" rel="noopener noreferrer">Open Link</a>@endif<div class="small text-secondary mt-1">{{ $notification->created_at?->diffForHumans() }}</div></div><div class="col-auto">@if(!$notification->read_at)<form method="POST" action="{{ route('notifications.read', $notification) }}" @if($notification->action_url) target="_blank" @endif>@csrf<button class="btn btn-sm btn-outline-primary">Mark read</button></form>@endif</div></div></div>@empty<div class="text-center text-secondary py-5">No notifications.</div>@endforelse</div><div class="card-footer">{{ $notifications->links() }}</div></div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.notification-message-content a[href]').forEach((link) => {
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
    });
});
</script>
@endsection
