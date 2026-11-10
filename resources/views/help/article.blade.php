@extends('layouts.app')
@section('title',$article['title'].' · Help Center')
@section('page-header','')
@section('content')
@vite(['resources/css/pages/help-center.css','resources/js/helpCenter.js'])
<div class="help-center help-reader">
    <nav class="help-breadcrumbs" aria-label="Breadcrumb"><a href="{{ route('help.index') }}"><i class="ti ti-lifebuoy" aria-hidden="true"></i> Help Center</a><i class="ti ti-chevron-right" aria-hidden="true"></i><a href="{{ route('help.index',['category'=>$article['category']]) }}">{{ \App\Support\HelpCenter::CATEGORIES[$article['category']]['label'] }}</a></nav>
    <div class="help-reader-layout">
        <main class="help-document">
            <header class="help-document-header"><div class="help-eyebrow">STEP-BY-STEP GUIDE · {{ $article['minutes'] }} MIN READ</div><h1>{{ $article['title'] }}</h1><p>{{ $article['summary'] }}</p>
                <div class="help-document-actions">
                    @if($article['route'])<a class="btn btn-primary" href="{{ route($article['route_prefix'].$article['route']) }}">Open page <i class="ti ti-arrow-up-right ms-2" aria-hidden="true"></i></a>@endif
                    <button class="btn btn-outline-secondary" type="button" data-help-print hidden><i class="ti ti-printer me-2" aria-hidden="true"></i>Print guide</button>
                    <a class="btn btn-ghost-secondary" href="{{ route('help.index') }}"><i class="ti ti-search me-2" aria-hidden="true"></i>Search help</a>
                </div>
            </header>
            <div class="help-prose" data-help-body>{!! $html !!}</div>
            <footer class="help-document-footer"><i class="ti ti-info-circle" aria-hidden="true"></i><span>Missing a menu or action? Ask your administrator to review your permissions and campus access.</span></footer>
        </main>
        <aside class="help-reader-aside">
            <nav class="help-toc" data-help-toc aria-label="On this page" hidden><h2>On this page</h2><div data-help-toc-links></div></nav>
            @if($related)<section class="help-related"><h2>Related guides</h2>@foreach($related as $item)<a href="{{ route('help.show',$item['slug']) }}">{{ $item['title'] }}<i class="ti ti-chevron-right" aria-hidden="true"></i></a>@endforeach</section>@endif
        </aside>
    </div>
</div>
@endsection
