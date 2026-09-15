@extends('layouts.app')

@section('title', $title)

@section('page-header')
    <div class="container-fluid">
        <div class="row g-2 align-items-center">
            <div class="col">
                <div class="page-pretitle">{{ $pretitle }}</div>
                <h2 class="page-title">{{ $title }}</h2>
            </div>
        </div>
    </div>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <div class="d-flex align-items-start gap-3">
                <div class="avatar avatar-lg bg-primary-lt text-primary">
                    <i class="ti {{ $icon }} fs-1"></i>
                </div>
                <div>
                    <h3 class="mb-1">{{ $title }}</h3>
                    <p class="text-secondary mb-0">{{ $description }}</p>
                </div>
            </div>
        </div>
    </div>
@endsection
