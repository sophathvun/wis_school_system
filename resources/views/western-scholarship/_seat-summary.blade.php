<div class="scholarship-seat-summary" data-seat-summary aria-label="Seat plan totals" aria-live="polite">
    <span class="scholarship-seat-summary-total"><span>Total Students: <strong>{{ number_format($seatSummary['total']??0) }}</strong></span><small>Male: <strong>{{ number_format($seatSummary['male']??0) }}</strong><span aria-hidden="true"> | </span>Female: <strong>{{ number_format($seatSummary['female']??0) }}</strong></small></span>
    @foreach($seatSummary['centers']??[] as $center)
        <span class="scholarship-seat-summary-divider" aria-hidden="true"></span>
        <span class="scholarship-seat-summary-center"><span>{{ $center['name'] }}</span><small>Exam Rooms: <strong>{{ number_format($center['rooms']) }}</strong></small></span>
    @endforeach
</div>
