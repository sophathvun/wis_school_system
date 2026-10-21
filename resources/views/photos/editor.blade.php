@extends('layouts.app')
@section('title', 'Photo Editor')
@section('content')
    @include('photos._workspace')
    @include('photos._editor', ['editorId' => 'photoEditorModal', 'photoTitle' => 'Photo Editor',
        'backgroundUrl' => route('photo-editor.background', [], false), 'allowPhotoSizes' => true,
        'sizeUrl' => route('photo-editor.size', [], false)])
@endsection
@push('styles')
    @vite('resources/css/pages/photo-editor.css')
@endpush
@push('scripts')
    @vite('resources/js/photoEditor.js')
@endpush
