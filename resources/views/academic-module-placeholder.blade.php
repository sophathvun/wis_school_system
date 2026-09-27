@extends('layouts.app')

@section('title', $title)

@php
    $needsNavbarSpace = true;
    $placeholderIconImageUrl = !empty($iconImage)
        ? asset($iconImage) . '?v=' . (file_exists(public_path($iconImage)) ? filemtime(public_path($iconImage)) : time())
        : null;
@endphp

@section('page-header')
    <div class="container-fluid {{ $needsNavbarSpace ? 'pt-3' : '' }}">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">{{ $pretitle }}</div>
                <h2 class="page-title">{{ $title }}</h2>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="card {{ $needsNavbarSpace ? 'mt-2' : '' }}">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <div class="avatar avatar-lg bg-primary-lt text-primary">
                    @if ($placeholderIconImageUrl)
                        <img src="{{ $placeholderIconImageUrl }}" alt="{{ $title }}" style="display:block;width:2rem;height:2rem;object-fit:contain;background:transparent;border:0;border-radius:0;box-shadow:none;">
                    @else
                        <i class="ti {{ $icon }} fs-1"></i>
                    @endif
                </div>
                <div>
                    <h3 class="mb-1">{{ $title }}</h3>
                    <p class="text-secondary mb-0">{{ $description }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
