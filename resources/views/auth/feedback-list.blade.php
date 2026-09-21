@extends('layouts.app')

@section('title', 'Feedback List')

@section('content')
    <div class="page-header">
        <div class="row align-items-center">
            <div class="col">
                <h2>Feedback List</h2>
                <div class="text-secondary">Review feedback submitted by system users.</div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-vcenter card-table">
                <thead>
                    <tr>
                        <th style="width: 70px">No.</th>
                        <th>User</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th style="width: 170px">Screenshot</th>
                        <th style="width: 150px">Status</th>
                        <th style="width: 180px">Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($feedbackItems as $item)
                        <tr>
                            <td>{{ $feedbackItems->firstItem() + $loop->index }}</td>
                            <td>
                                <div class="fw-semibold">{{ $item->user?->name ?? 'Unknown user' }}</div>
                                <div class="text-secondary small">{{ $item->user?->department?->name ?? 'No department' }}</div>
                            </td>
                            <td>{{ $item->subject }}</td>
                            <td>
                                <div class="feedback-message-preview">{!! $item->message !!}</div>
                            </td>
                            <td>
                                @if ($item->attachment_path)
                                    @php($attachmentUrl = asset('storage/' . $item->attachment_path))
                                    <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="d-inline-block">
                                        <img class="feedback-list-attachment" src="{{ $attachmentUrl }}" alt="Feedback screenshot">
                                    </a>
                                    <div class="mt-1">
                                        <a href="{{ $attachmentUrl }}" target="_blank" rel="noopener" class="small">
                                            <i class="ti ti-eye me-1"></i>View image
                                        </a>
                                    </div>
                                @else
                                    <span class="text-secondary">No image</span>
                                @endif
                            </td>
                            <td><span class="badge bg-blue-lt text-capitalize">{{ $item->status }}</span></td>
                            <td>{{ $item->created_at?->format('d-M-Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-secondary py-5">No feedback submitted yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($feedbackItems->hasPages())
            <div class="card-footer d-flex justify-content-end">
                {{ $feedbackItems->links() }}
            </div>
        @endif
    </div>
@endsection

@vite('resources/css/pages/feedback.css')
