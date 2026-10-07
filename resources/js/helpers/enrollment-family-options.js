export const createEnrollmentFamilyOptionsLoader = (fetcher = fetch) => {
    const requests = new Map();
    return {
        load(term = "") {
            const query = String(term).trim().slice(0, 120);
            if (requests.has(query)) return requests.get(query);
            const request = Promise.resolve().then(() => fetcher(
                `/student-enrollments/quick-options${query ? `?q=${encodeURIComponent(query)}` : ""}`,
                { headers: { Accept: "application/json" } },
            )).then(async (response) => {
                if (!response.ok) throw new Error("Unable to load families. Please try again.");
                const data = await response.json();
                if (!Array.isArray(data.families)) throw new Error("Unable to load families. Please try again.");
                return data;
            }).catch((error) => {
                // An unsuccessful request must not prevent the next attempt.
                if (requests.get(query) === request) requests.delete(query);
                throw error;
            });
            requests.set(query, request);
            if (requests.size > 20) requests.delete(requests.keys().next().value);
            return request;
        },
        clear() { requests.clear(); },
    };
};
