<footer class="footer footer-transparent d-print-none">
    <div class="container-fluid">
        <div class="text-center">
            @if(!empty($branding?->footer_logo_path))
                <img src="{{ asset('storage/'.$branding->footer_logo_path) }}" alt="Footer logo"
                     style="max-height:32px;max-width:140px;object-fit:contain;">
            @endif
            @if(!empty($branding?->footer_text))
                <div class="text-secondary small mt-2">{{ $branding->footer_text }}</div>
            @endif
        </div>
    </div>
</footer>
