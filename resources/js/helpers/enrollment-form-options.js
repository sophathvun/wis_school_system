export async function fetchEnrollmentFormOptions(fetcher = fetch) {
    const response = await fetcher('/student-enrollments/options', { headers: { Accept: 'application/json' } });
    if (!response.ok || response.redirected) throw new Error('Unable to load enrollment options. Refresh the page and try again.');
    const options = await response.json();
    if (!Array.isArray(options.academicYears) || !Array.isArray(options.countries))
        throw new Error('Enrollment options could not be loaded. Refresh the page and try again.');
    return options;
}

export async function fetchEnrollmentDocumentOptions(fetcher = fetch) {
    try {
        const response = await fetcher('/student-documents/options', { headers: { Accept: 'application/json' } });
        if (!response.ok || response.redirected) return { types: [] };
        const data = await response.json();
        return Array.isArray(data.types) ? data : { types: [] };
    } catch {
        return { types: [] };
    }
}
