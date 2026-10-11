export function skippingReportUrl(source, filters, statistics = false) {
    const url = new URL(source, location.href);
    if (filters) {
        const params = new URLSearchParams(filters);
        params.delete('per_page');
        url.search = params.toString();
    }
    if (statistics) url.searchParams.set('status', 'approved');
    return url;
}
