@extends('layouts.app')

@section('title', 'Messages')

@section('content')
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="ti ti-messages fs-1 text-primary" aria-hidden="true"></i>
            <h2 class="mt-3">Messages</h2>
            <p class="text-secondary">Read and reply using the chat window.</p>
            <button type="button" class="btn btn-primary" id="chat-open-notification">
                <i class="ti ti-messages me-2" aria-hidden="true"></i> Open Messages
            </button>
        </div>
    </div>
@endsection
