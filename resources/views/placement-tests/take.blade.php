@extends('layouts.app')

@section('title', 'Placement Test')

@section('content')
    <main id="placementTakeApp" class="pt-take-shell" data-token="{{ $token }}">
        <div class="pt-take-backdrop"></div>
        <header class="pt-take-header">
            <div class="pt-take-brand"><span><i class="ti ti-school"></i></span>
                <div><strong>Western International School</strong><small>Student Placement Assessment</small></div>
            </div>
            <div class="pt-secure"><i class="ti ti-shield-check"></i> Secure test session</div>
        </header>
        <section class="pt-take-card">
            <div id="ptTakeContent" class="pt-take-content"></div>
        </section>
        <footer class="pt-take-footer">Your responses are protected and used only for school placement.</footer>
    </main>
    <template id="ptPublicData">@json($public)</template>
    @vite(['resources/css/pages/placement-test-take.css', 'resources/js/placementTestTake.js'])
@endsection
