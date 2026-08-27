<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $staff->name }} - Staff Name Card</title>
    @vite('resources/css/pages/staff-card.css')
    <style>
        .staff-card-actions button,.staff-card-actions a,.staff-card-share button,.staff-card-share a{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:.65rem .9rem;border:1px solid #dbe5f0;border-radius:13px;background:#fff;color:#203a5f;font:inherit;font-weight:700;cursor:pointer;appearance:none}
        .staff-card-icon-button,.staff-card-share-icon{width:44px;min-width:44px;padding:0!important;appearance:none;line-height:1}.staff-card-qr img{width:190px}
        .staff-card-brand{flex-direction:column;align-items:center;justify-content:center;gap:.2rem;padding:0 .45rem .35rem;text-align:center;grid-column:1/-1}.staff-card-brand img{width:190px;height:137px}.staff-card{background:var(--staff-card-background,#206bc4)!important;padding:4px 2rem 2rem}.staff-card-meta{display:none}.staff-card-contact-meta{display:grid;gap:3px!important;row-gap:3px!important}.staff-card-contact-meta div{display:flex;align-items:center;gap:.45rem;padding:0!important;margin:0!important;line-height:1!important;border-radius:999px;background:transparent}.staff-card-contact-meta svg{width:16px;height:16px;flex:0 0 16px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .staff-card{width:min(100%,520px)!important;height:auto!important;min-height:0!important;aspect-ratio:90/54!important;grid-template-columns:30% minmax(0,1fr)!important}.staff-card-page.is-portrait{--staff-card-width:54mm;--staff-card-height:90mm}.staff-card-page.is-portrait .staff-card{width:min(100%,320px)!important;height:auto!important;min-height:0!important;grid-template-columns:30% minmax(0,1fr)!important;grid-template-rows:auto 1fr;aspect-ratio:54/90!important}.staff-card-photo,.staff-card-page.is-portrait .staff-card-photo{width:100%;height:auto;aspect-ratio:3/4}
        .staff-card-icon-button svg,.staff-card-share-icon svg,.staff-card-share>span svg{width:20px;height:20px;fill:none;stroke:currentColor;stroke-linecap:round;stroke-linejoin:round;stroke-width:1.8}
        .staff-card-share>span{display:inline-flex;align-items:center;gap:.45rem}.staff-card-share>span svg{width:17px;height:17px}
        .staff-card-share-icon.telegram{color:#168acd}.staff-card-share-icon.whatsapp{color:#18a957}.staff-card-share-icon.facebook{color:#4267b2}.staff-card-share-icon.copy{color:#64748b}
        .staff-card{padding-top:4px!important}.staff-card-meta{display:none!important}.staff-card-photo{grid-column:1;grid-row:2}.staff-card-info{grid-column:2;grid-row:2;justify-content:flex-start!important}.staff-card h1{font-size:1.3rem!important;text-transform:uppercase}.staff-card-position{font-size:.85rem!important;margin:.25rem 0 .4rem!important}.staff-card-contact-meta div{justify-content:flex-start;padding-inline:0;background:transparent!important;color:#206bc4}.staff-card-qr-inline{display:flex!important;grid-column:1 / -1;grid-row:3;flex-direction:column;align-items:center;justify-content:center;gap:.2rem;margin-top:2rem}.staff-card-qr-inline img{width:150px!important;height:150px!important;display:block;transform:none;object-fit:contain}.staff-card-shell>.staff-card-qr{display:none!important}.staff-card-page.is-portrait .staff-card{grid-template-columns:30% minmax(0,1fr)!important}.staff-card-brand img{transform:translateY(-32px);margin-bottom:-32px}
        .staff-card-page.is-light .staff-card{color:#203a5f}.staff-card-page.is-light .staff-card-eyebrow,.staff-card-page.is-light .staff-card-position{color:rgba(32,58,95,.72)}.staff-card-page.is-light .staff-card-contact-meta div{background:rgba(32,58,95,.1)}
        @media print{
            .staff-card-page:not(.is-portrait) .staff-card{width:90mm!important;height:54mm!important}
            .staff-card-page.is-portrait .staff-card{width:54mm!important;height:90mm!important}
            .staff-card{padding:2%!important;gap:1%!important;grid-template-rows:25% 32% 43%!important;align-content:stretch!important;overflow:hidden!important}
            .staff-card-brand{height:auto!important;padding:0!important;gap:0!important;overflow:visible!important}
            .staff-card-brand img{width:60%!important;height:auto!important;max-height:100%!important;transform:none!important;margin:0!important;object-fit:contain!important}
            .staff-card-photo{height:90%!important;align-self:center!important;border-width:2px!important;border-radius:12px!important;font-size:1.5rem!important}
            .staff-card-info{min-width:0!important;overflow:hidden!important;align-self:center!important}
            .staff-card h1{font-size:1.3rem!important;line-height:1.04!important;letter-spacing:-.02em!important}
            .staff-card-position{font-size:.85rem!important;margin:.25rem 0 .4rem!important;line-height:1!important}
            .staff-card-contact-meta{gap:3px!important;row-gap:3px!important}
            .staff-card-contact-meta div{gap:.35rem!important;font-size:.85rem!important;line-height:1!important;white-space:nowrap!important}
            .staff-card-contact-meta svg{width:14px!important;height:14px!important;flex-basis:14px!important}
            .staff-card-qr-inline{margin-top:0!important;gap:.2rem!important;padding:0!important;align-self:center!important}
            .staff-card-qr-inline img{width:42%!important;height:auto!important;max-height:90%!important}
            .staff-card-qr-inline span{font-size:.85rem!important;line-height:1!important}
        }
    </style>
</head>

<body>
    @php
        $logoPath = $branding->report_logo_1_path ?? $branding->login_logo_path ?? $branding->sidebar_logo_path ?? null;
        $campusNames = $staff->campuses->pluck('campus_name_en')->filter()->join(', ');
        $shareTitle = trim(($staff->name ?: 'Staff Name Card') . ' - Western International School');
        $shareMessage = $shareTitle . ' ' . $publicCardUrl;
        $orientation = in_array($staff->public_card_orientation ?: 'landscape', ['portrait', 'landscape'], true)
            ? ($staff->public_card_orientation ?: 'landscape')
            : 'landscape';
        $background = preg_match('/^#[0-9A-Fa-f]{6}$/', $staff->public_card_background ?? '')
            ? $staff->public_card_background
            : '#206bc4';
        $backgroundRgb = sscanf(ltrim($background, '#'), '%02x%02x%02x') ?: [32, 107, 196];
        $isLightBackground = (($backgroundRgb[0] * 299) + ($backgroundRgb[1] * 587) + ($backgroundRgb[2] * 114)) > 180000;
        $printMode = request()->boolean('print');
        $printCopies = $printMode ? 9 : 1;
    @endphp

    <style>
        @media print {
            @page {
                size: A4 {{ $orientation === 'portrait' ? 'portrait' : 'landscape' }};
                margin: 0;
            }
        }
    </style>

    <style>
        @media print {
            @page { size: A4 {{ $orientation === 'portrait' ? 'portrait' : 'landscape' }}; margin: 0; }
            body { background: #fff !important; }
            .staff-card-page { display: block !important; min-height: 0 !important; padding: 9.5mm !important; }
            .staff-card-shell { width: 100% !important; padding: 0 !important; border: 0 !important; border-radius: 0 !important; background: transparent !important; box-shadow: none !important; display: grid !important; gap: 4mm !important; }
            .staff-card-page.is-portrait .staff-card-shell { grid-template-columns: repeat(3, 54mm) !important; grid-auto-rows: 90mm !important; }
            .staff-card-page.is-landscape .staff-card-shell { grid-template-columns: repeat(3, 90mm) !important; grid-auto-rows: 54mm !important; }
            .staff-card { width: 100% !important; height: 100% !important; min-height: 0 !important; margin: 0 !important; padding: 2% !important; gap: 1% !important; grid-template-rows: 25% 32% 43% !important; align-content: stretch !important; page-break-inside: avoid !important; break-inside: avoid !important; overflow: hidden !important; border: .25mm solid #cbd5e1 !important; border-radius: 2mm !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .staff-card-page.is-portrait .staff-card { width: 54mm !important; height: 90mm !important; flex: 0 0 54mm !important; }
            .staff-card-page.is-landscape .staff-card { width: 90mm !important; height: 54mm !important; flex: 0 0 90mm !important; }
            .staff-card-brand { display: flex !important; visibility: visible !important; position: relative !important; z-index: 5 !important; height: auto !important; padding: 0 !important; gap: 0 !important; overflow: visible !important; }
            .staff-card-brand img { display: block !important; visibility: visible !important; width: 60% !important; height: auto !important; max-height: 100% !important; transform: none !important; margin: 0 !important; object-fit: contain !important; }
            .staff-card-photo { height: 90% !important; align-self: center !important; border-width: 2px !important; border-radius: 12px !important; font-size: 1.5rem !important; }
            .staff-card-info { min-width: 0 !important; overflow: hidden !important; align-self: center !important; }
            .staff-card h1 { font-size: 1.3rem !important; line-height: 1.04 !important; letter-spacing: -.02em !important; }
            .staff-card-position { font-size: .85rem !important; margin: .25rem 0 .4rem !important; line-height: 1 !important; }
            .staff-card-contact-meta { gap: 3px !important; row-gap: 3px !important; }
            .staff-card-contact-meta div { gap: .35rem !important; font-size: .85rem !important; line-height: 1 !important; white-space: nowrap !important; }
            .staff-card-contact-meta svg { width: 14px !important; height: 14px !important; flex-basis: 14px !important; }
            .staff-card-qr-inline { display: flex !important; visibility: visible !important; position: relative !important; z-index: 5 !important; margin-top: 0 !important; gap: .2rem !important; padding: 0 !important; align-self: center !important; }
            .staff-card-qr-inline img { display: block !important; visibility: visible !important; width: 42% !important; height: auto !important; max-height: 90% !important; }
            .staff-card-qr-inline span { font-size: .85rem !important; line-height: 1 !important; }
            .staff-card-actions, .staff-card-share, .staff-card-shell > .staff-card-qr { display: none !important; }
        }
    </style>

    <main class="staff-card-page is-{{ $orientation }} {{ $isLightBackground ? 'is-light' : '' }}"
        style="page: {{ $orientation === 'portrait' ? 'staff-card-portrait' : 'staff-card-landscape' }};">
        <section class="staff-card-shell">
            @for ($copy = 0; $copy < $printCopies; $copy++)
            <div class="staff-card" style="--staff-card-background: {{ $background }}; grid-template-columns: 30% minmax(0, 1fr);">
                <div class="staff-card-brand" style="grid-column: 1 / -1; grid-row: 1;">
                    @if ($logoPath)
                        <img src="{{ asset('storage/' . $logoPath) }}" alt="School Logo 1">
                    @endif
                </div>
                <div class="staff-card-photo" style="grid-column: 1; grid-row: 2; width: 100%; height: auto; aspect-ratio: 3 / 4;">
                    @if ($staff->photo_path)
                        <img src="{{ asset('storage/' . $staff->photo_path) }}" alt="{{ $staff->name }}">
                    @else
                        <span>{{ mb_substr($staff->name ?: 'S', 0, 1) }}</span>
                    @endif
                </div>

                <div class="staff-card-info" style="grid-column: 2; grid-row: 2;">
                    <h1>{{ $staff->name }}</h1>
                    <p class="staff-card-position">{{ $staff->position?->name ?: $staff->department?->name ?: 'Staff / Teacher' }}</p>

                    <div class="staff-card-meta" style="display: none !important;">
                        @if ($staff->department?->name)
                            <div><i>🏢</i><span>{{ $staff->department->name }}</span></div>
                        @endif
                        @if ($campusNames)
                            <div><i>📍</i><span>{{ $campusNames }}</span></div>
                        @endif
                        @if ($staff->phone)
                            <div><i>☎</i><span>{{ $staff->phone }}</span></div>
                        @endif
                        @if ($staff->email)
                            <div><i>✉</i><span>{{ $staff->email }}</span></div>
                        @endif
                    </div>

                    <div class="staff-card-contact-meta">
                        @if ($staff->phone)
                            <div><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h4l2 5-3 2a13 13 0 0 0 5 5l2-3 5 2v4c0 1-1 2-2 2C10 20 4 14 4 5c0-1 1-2 2-2z"/></svg><span>{{ $staff->phone }}</span></div>
                        @endif
                        @if ($staff->email)
                            <div><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3zM3 6l9 7 9-7"/></svg><span>{{ $staff->email }}</span></div>
                        @endif
                    </div>

                </div>
                <div class="staff-card-qr-inline" style="grid-column:1 / -1; grid-row:3;">
                    <img src="{{ $publicCardQrUrl }}" alt="QR code">
                    <span>Scan</span>
                </div>
            </div>
            @endfor

            <div class="staff-card-actions {{ $printMode ? 'is-print-hidden' : '' }}">
                <a class="staff-card-primary staff-card-icon-button" href="{{ $publicCardVcardUrl }}" title="Save contact" aria-label="Save contact">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14M4 4h16v16H4z"/></svg>
                </a>
                <a class="staff-card-icon-button" href="tel:{{ preg_replace('/\\s+/', '', $staff->phone ?? '') }}" @class(['is-disabled' => !$staff->phone]) title="Call" aria-label="Call">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 3h4l2 5-3 2a13 13 0 0 0 5 5l2-3 5 2v4c0 1-1 2-2 2C10 20 4 14 4 5c0-1 1-2 2-2z"/></svg>
                </a>
                <a class="staff-card-icon-button" href="mailto:{{ $staff->email }}" @class(['is-disabled' => !$staff->email]) title="Email" aria-label="Email">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5h18v14H3zM3 6l9 7 9-7"/></svg>
                </a>
                <button class="staff-card-icon-button" type="button" onclick="window.print()" title="Print" aria-label="Print">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></svg>
                </button>
            </div>

            <div class="staff-card-share {{ $printMode ? 'is-print-hidden' : '' }}">
                <span><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.7 13.5 6.6 4M8.7 10.5l6.6-4"/></svg> Share name card</span>
                <div>
                    <a class="staff-card-share-icon telegram" target="_blank" rel="noopener" title="Share on Telegram" aria-label="Share on Telegram"
                        href="https://t.me/share/url?url={{ urlencode($publicCardUrl) }}&text={{ urlencode($shareTitle) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 3-3 18-7-5-4 3 1-6L3 9zM8 15l8-7-9 5"/></svg></a>
                    <a class="staff-card-share-icon whatsapp" target="_blank" rel="noopener" title="Share on WhatsApp" aria-label="Share on WhatsApp"
                        href="https://wa.me/?text={{ urlencode($shareMessage) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 11.5a8 8 0 0 1-12 7L4 20l1.5-4A8 8 0 1 1 20 11.5zM8 8c.3 3 2 5 5 6l1-1-1-1-1 .4c-1-.5-1.7-1.2-2.2-2.2l.4-1-1-1z"/></svg></a>
                    <a class="staff-card-share-icon facebook" target="_blank" rel="noopener" title="Share on Facebook" aria-label="Share on Facebook"
                        href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode($publicCardUrl) }}"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 21v-8h3l.5-3H14V8c0-1 .4-2 2-2h1.7V3.2C17 3.1 16.1 3 15 3c-3 0-5 1.8-5 5v2H7v3h3v8z"/></svg></a>
                    <button class="staff-card-share-icon copy" type="button" id="copyStaffCardLink" data-url="{{ $publicCardUrl }}" title="Copy link" aria-label="Copy link"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="11" height="12" rx="2"/><path d="M16 8V6a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h3"/></svg></button>
                </div>
            </div>

            <div class="staff-card-qr {{ $printMode ? 'is-print-hidden' : '' }}">
                <img src="{{ $publicCardQrUrl }}" alt="QR code">
                <p>Scan to open this official contact card.</p>
            </div>
        </section>
    </main>

    <script>
        const isPrintPage = new URLSearchParams(window.location.search).get('print') === '1';
        const normalCardUrl = @json($publicCardUrl);

        if (isPrintPage) {
            window.addEventListener('load', () => setTimeout(() => window.print(), 300), { once: true });
            window.addEventListener('afterprint', () => {
                setTimeout(() => {
                    window.close();
                    setTimeout(() => {
                        if (!window.closed) {
                            window.location.replace(normalCardUrl);
                        }
                    }, 150);
                }, 50);
            }, { once: true });
        }

        document.getElementById('copyStaffCardLink')?.addEventListener('click', async event => {
            const url = event.currentTarget.dataset.url;
            try {
                await navigator.clipboard.writeText(url);
                event.currentTarget.textContent = 'Copied';
            } catch (error) {
                window.prompt('Copy this link', url);
            }
        });
    </script>
</body>

</html>
