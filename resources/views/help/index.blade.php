@extends('layouts.app')
@section('title','Help Center')
@section('page-header','')
@section('content')
@vite(['resources/css/pages/help-center.css','resources/js/helpCenter.js'])
<div class="help-center">
    <header class="help-hero">
        <div class="help-eyebrow"><i class="ti ti-lifebuoy" aria-hidden="true"></i> SCHOOL SYSTEM · HELP CENTER</div>
        <h1>How can we help?</h1>
        <p>Clear instructions for your everyday work. Find a task, follow the steps, and get back to your page.</p>
        <form method="GET" action="{{ route('help.index') }}" class="help-search" role="search">
            @if($category)<input type="hidden" name="category" value="{{ $category }}">@endif
            <label class="visually-hidden" for="helpSearch">Search help articles</label>
            <i class="ti ti-search" aria-hidden="true"></i>
            <input id="helpSearch" name="q" type="search" value="{{ $query }}" maxlength="200" placeholder="Search a task, page name, or Khmer label…" data-search-normalized="true">
            <button type="submit">Search</button>
        </form>
        <div class="help-hero-hint"><i class="ti ti-book" aria-hidden="true"></i> Start with Getting started or explore the examination guides.</div>
    </header>
    <div class="help-layout">
        <aside class="help-navigation" aria-label="Help categories">
            <h2>Browse topics</h2>
            <a href="{{ route('help.index',array_filter(['q'=>$query])) }}" @class(['is-current'=>$category==='']) @if($category==='')aria-current="page"@endif><i class="ti ti-layout-grid" aria-hidden="true"></i><span>All guides</span><small>{{ count($available) }}</small></a>
            @foreach($categories as $key=>$item)
            <a href="{{ route('help.index',array_filter(['category'=>$key,'q'=>$query])) }}" @class(['is-current'=>$category===$key]) @if($category===$key)aria-current="page"@endif><i class="ti {{ $item['icon'] }}" aria-hidden="true"></i><span>{{ $item['label'] }}</span><small>{{ collect($available)->where('category',$key)->count() }}</small></a>
            @endforeach
            <div class="help-navigation-note"><i class="ti ti-shield-check" aria-hidden="true"></i><p>Guides shown here follow your module access. Editing and exports still require their own permissions.</p></div>
        </aside>
        <main class="help-results" id="helpResults">
            <div class="help-section-heading"><div><span class="help-eyebrow">{{ $query!==''?'SEARCH RESULTS':'YOUR KNOWLEDGE LIBRARY' }}</span><h2>{{ $category?($categories[$category]['label']??'Guides'):'Explore the guides' }}</h2></div><span class="help-count">{{ count($articles) }} {{ count($articles)===1?'guide':'guides' }}</span></div>
            @if($query!==''||$category!=='')<div class="help-search-context">@if($query!=='')Results for <strong>“{{ $query }}”</strong>@endif <a href="{{ route('help.index') }}">Clear filters</a></div>@endif
            <div class="help-article-grid">
            @forelse($articles as $article)
                <a class="help-article-card" href="{{ route('help.show',$article['slug']) }}">
                    <span class="help-card-icon"><i class="ti {{ \App\Support\HelpCenter::CATEGORIES[$article['category']]['icon'] }}" aria-hidden="true"></i></span>
                    <div class="help-card-meta">{{ \App\Support\HelpCenter::CATEGORIES[$article['category']]['label'] }} <span>· {{ $article['minutes'] }} min read</span></div>
                    <h3>{{ $article['title'] }}</h3><p>{{ $article['summary'] }}</p>
                    <span class="help-card-link">Read guide <i class="ti ti-arrow-right" aria-hidden="true"></i></span>
                </a>
            @empty
                <div class="help-empty"><i class="ti ti-search" aria-hidden="true"></i><h3>No guides found</h3><p>Try a shorter phrase such as “certificate”, “date”, or “ពិន្ទុ”, or clear the category filter.</p><a class="btn btn-primary" href="{{ route('help.index') }}">Browse available guides</a></div>
            @endforelse
            </div>
            <div class="help-library-note"><i class="ti ti-info-circle" aria-hidden="true"></i><span>This collection covers getting started, Students, National Exams, Attendance, and Administrator tools. Guides for other modules will be added as their workflows are reviewed.</span></div>
        </main>
    </div>
</div>
@endsection
