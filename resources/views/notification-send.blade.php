@extends('layouts.app')
@section('title', 'Send Notification')
@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">Communication</div>
                <h2 class="page-title">Send Notification</h2>
            </div>
            <div class="col-auto"><a class="btn btn-outline-primary" href="{{ route('notifications.manage') }}">Notification
                    Management</a></div>
        </div>
    </div>
@endsection
@section('content')

    @php
        $oldDepartmentIds = collect(old('department_ids', []))->map(fn($id) => (string) $id);
        $oldRecipientIds = collect(old('recipient_ids', []))->map(fn($id) => (string) $id);
        $staffProfiles = $users->mapWithKeys(
            fn($user) => [
                (string) $user->id => [
                    'id' => (string) $user->id,
                    'name' => $user->name ?: $user->username,
                    'username' => $user->username,
                    'email' => $user->email,
                    'department_id' => $user->department_id ? (string) $user->department_id : '',
                    'position' => $user->position?->name ?: 'No position',
                    'photo' => $user->photo_path ? asset('storage/' . $user->photo_path) : null,
                    'online' => $user->last_seen_at?->greaterThan(now()->subMinutes(5)) ?? false,
                ],
            ],
        );
    @endphp
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif
    <div class="card" data-notification-send-page data-staff-profiles='@json($staffProfiles)'
        data-upload-url="{{ url('/settings/notifications/upload-image') }}" data-csrf="{{ csrf_token() }}">
        <div class="card-header">
            <h3 class="card-title">Create Notification</h3>
        </div>
        <form method="POST" action="{{ route('notifications.send.save') }}">@csrf<div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">Notification Type</label><select class="form-select"
                            name="type">
                            <option value="announcement">Announcement</option>
                            <option value="system">System</option>
                            <option value="enrollment">Enrollment</option>
                            <option value="approval">Approval</option>
                        </select></div>
                    <div class="col-md-8"><label class="form-label">Title</label><input class="form-control" name="title"
                            value="{{ old('title') }}" required></div>
                    <div class="col-12">
                        <div class="notification-editor">
                            <div class="notification-editor-toolbar"><button class="btn btn-outline-secondary"
                                    type="button" data-editor-command="bold" title="Bold"><i
                                        class="ti ti-bold"></i></button><button class="btn btn-outline-secondary"
                                    type="button" data-editor-command="italic" title="Italic"><i
                                        class="ti ti-italic"></i></button><button class="btn btn-outline-secondary"
                                    type="button" data-editor-command="underline" title="Underline"><i
                                        class="ti ti-underline"></i></button><button class="btn btn-outline-secondary"
                                    type="button" data-editor-command="insertUnorderedList" title="Bullet list"><i
                                        class="ti ti-list"></i></button><button class="btn btn-outline-secondary"
                                    type="button" data-editor-command="insertOrderedList" title="Numbered list"><i
                                        class="ti ti-list-numbers"></i></button><button class="btn btn-outline-secondary"
                                    type="button" data-editor-align="justifyLeft" title="Align left"><i
                                        class="ti ti-align-left"></i></button><button class="btn btn-outline-secondary"
                                    type="button" data-editor-align="justifyCenter" title="Align center"><i
                                        class="ti ti-align-center"></i></button><button class="btn btn-outline-secondary"
                                    type="button" data-editor-align="justifyRight" title="Align right"><i
                                        class="ti ti-align-right"></i></button><button class="btn btn-outline-secondary"
                                    type="button" data-editor-link title="Insert link"><i class="ti ti-link"></i></button>
                                <div class="dropdown"><button class="btn btn-outline-secondary dropdown-toggle"
                                        type="button" data-bs-toggle="dropdown" aria-expanded="false"
                                        title="Insert layout"><i class="ti ti-layout-grid"></i></button>
                                    <div class="dropdown-menu dropdown-menu-end p-1 align-menu"><button
                                            class="dropdown-item" type="button" data-editor-template="table">Insert
                                            Table</button><button class="dropdown-item" type="button"
                                            data-editor-template="columns">Picture + Description</button><button
                                            class="dropdown-item" type="button" data-editor-template="address">Address
                                            Block</button></div>
                                </div><button class="btn btn-primary btn-wide" type="button" data-editor-image><i
                                        class="ti ti-photo-up me-1"></i> Image</button><input class="d-none"
                                    type="file" accept="image/*" data-notification-image-input>
                            </div>
                            <div class="notification-image-tools" data-notification-image-tools><span
                                    class="text-secondary small me-1">Image size</span><button
                                    class="btn btn-outline-primary" type="button"
                                    data-image-size="25%">25%</button><button class="btn btn-outline-primary"
                                    type="button" data-image-size="50%">50%</button><button
                                    class="btn btn-outline-primary" type="button"
                                    data-image-size="75%">75%</button><button class="btn btn-outline-primary"
                                    type="button" data-image-size="100%">100%</button><button
                                    class="btn btn-outline-secondary" type="button"
                                    data-image-size="auto">Auto</button><button class="btn btn-outline-danger ms-auto"
                                    type="button" data-image-remove><i class="ti ti-trash me-1"></i> Remove</button>
                            </div>
                            <div class="notification-editor-body" contenteditable="true" data-notification-editor
                                aria-label="Notification message" data-placeholder="Write notification message..."></div>
                            <div class="notification-editor-help"><span>Supports formatted text and
                                    images.</span><span>Click an image to resize or remove it.</span></div>
                        </div>
                        <textarea class="d-none" name="message" data-notification-message>{{ old('message') }}</textarea>
                    </div>
                    <div class="col-12"><label class="form-label">Action URL <span
                                class="text-secondary">(optional)</span></label><input class="form-control"
                            type="url" name="action_url" value="{{ old('action_url') }}" placeholder="https://...">
                    </div>
                    <div class="col-12"><label class="form-check"><input class="form-check-input" type="checkbox"
                                name="send_to_all" value="1" id="sendToAll" @checked(old('send_to_all'))><span
                                class="form-check-label">Send to all active users</span></label></div>
                    <div class="col-md-6" id="departmentBox"><select class="form-select d-none" name="department_ids[]"
                            multiple size="7">
                            @foreach ($departments as $department)
                                <option value="{{ $department->id }}" @selected($oldDepartmentIds->contains((string) $department->id))>{{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-secondary d-none">Select one or multiple departments.</small>
                    </div>
                    <div class="col-md-6" id="recipientBox"><select class="form-select" name="recipient_ids[]" multiple
                            size="7">
                            @foreach ($users as $user)
                                <option value="{{ $user->id }}" @selected($oldRecipientIds->contains((string) $user->id))>{{ $user->name }} —
                                    {{ $user->username }} — {{ $user->email }}</option>
                            @endforeach
                        </select><small class="text-secondary">Staff already covered by selected departments are disabled
                            to avoid duplicate notification.</small></div>
                </div>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary"><i class="ti ti-send me-1"></i> Send
                    Notification</button></div>
        </form>
    </div>
    @vite('resources/js/notificationSend.js')
    @vite('resources/css/pages/notification-send.css')
@endsection
