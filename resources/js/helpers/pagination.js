// Pagination
export const renderPagination = (res, selector, perPageSelector, fetchFun) => {
    const container = document.getElementById(selector);
    const perPageInput = document.getElementById(perPageSelector);
    if (!container || !perPageInput) return;
    const perPage = perPageInput.value;

    const pages = [];
    const addPage = (page) => pages.push(page);
    const addEllipsis = () => pages.push("ellipsis");

    if (res.last_page <= 5) {
        for (let page = 1; page <= res.last_page; page++) addPage(page);
    } else if (res.current_page <= 3) {
        [1, 2, 3].forEach(addPage);
        addEllipsis();
        addPage(res.last_page);
    } else if (res.current_page >= res.last_page - 2) {
        addPage(1);
        addEllipsis();
        for (let page = res.last_page - 2; page <= res.last_page; page++) addPage(page);
    } else {
        addPage(1);
        addEllipsis();
        [res.current_page - 1, res.current_page, res.current_page + 1].forEach(addPage);
        addEllipsis();
        addPage(res.last_page);
    }

    let html = `<div class="premium-pagination">`;
    html += `<ul class="pagination premium-pagination-list m-0">`;

    // Previous button
    html += `
        <li class="page-item ${res.current_page === 1 ? "disabled" : ""}">
            <a class="page-link" href="#" tabindex="-1" aria-disabled="true" data-page="${res.current_page - 1}">
                <i class="ti ti-chevron-left icon icon-1"></i>
            </a>
        </li>`;

    pages.forEach((page) => {
        if (page === "ellipsis") {
            html += `<li class="premium-pagination-ellipsis" aria-hidden="true">…</li>`;
            return;
        }
        html += `<li class="page-item ${page === res.current_page ? "active" : ""}">
            <a class="page-link" href="#" data-page="${page}" aria-label="Page ${page}">${page}</a>
        </li>`;
    });

    // Next button
    html += `
        <li class="page-item ${res.current_page === res.last_page ? "disabled" : ""}">
            <a class="page-link" href="#" tabindex="-1" aria-disabled="true" data-page="${res.current_page + 1}">
                <i class="ti ti-chevron-right icon icon-1"></i>
            </a>
        </li>`;

    html += `</ul>
        <p class="premium-pagination-info m-0">Showing <strong>${res.from ?? 0} to ${res.to ?? 0}</strong> of <strong>${res.total} entries</strong></p>
        <div class="premium-pagination-controls">
            <label class="premium-pagination-select">
                <select class="form-select form-select-sm" aria-label="Entries per page">
                    ${[10, 25, 50, 100].map((value) => `<option value="${value}" ${String(value) === String(perPage) ? "selected" : ""}>${value} / page</option>`).join("")}
                </select>
            </label>
            <label class="premium-pagination-goto">
                <span>Go to</span>
                <input type="number" class="form-control form-control-sm" min="1" max="${res.last_page}" value="${res.current_page}" aria-label="Go to page">
                <span>Page</span>
            </label>
        </div>
    </div>`;
    container.innerHTML = html;

    // Event listener for pagination
    container.querySelectorAll(".page-link").forEach((link) => {
        link.addEventListener("click", (e) => {
            e.preventDefault();

            const page = parseInt(link.dataset.page);

            if (page < 1 || page > res.last_page || page === res.current_page) {
                return;
            }

            fetchFun(page, perPage);
        });
    });

    const pageSizeSelect = container.querySelector(".premium-pagination-controls select");
    pageSizeSelect?.addEventListener("change", (event) => {
        perPageInput.value = event.target.value;
        fetchFun(1, parseInt(event.target.value));
    });

    const goToInput = container.querySelector(".premium-pagination-goto input");
    goToInput?.addEventListener("change", (event) => {
        const page = Math.min(res.last_page, Math.max(1, parseInt(event.target.value) || 1));
        event.target.value = page;
        if (page !== res.current_page) fetchFun(page, perPage);
    });
};

// Page information
export const renderPageInfo = (res) => {
    const container = document.getElementById("pageInfor-container");
    const paginationInfo = document.querySelector(".premium-pagination-info");
    if (paginationInfo) {
        paginationInfo.innerHTML = `Showing <strong>${res.from ?? 0} to ${res.to ?? 0}</strong> of <strong>${res.total} entries</strong>`;
        return;
    }
    if (!container) return;
    let html = `
            <p class="m-0 text-secondary">Showing <strong>${res.from} to ${res.to}</strong> of <strong>${res.total} entries</strong></p>
    `;
    container.innerHTML = html;
};
