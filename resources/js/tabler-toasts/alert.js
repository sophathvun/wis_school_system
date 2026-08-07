export const showToast = (type = 'success', message = '') => {
    let toastContainer = document.getElementById('toast-container');

    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toast-container';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '1080';
        document.body.appendChild(toastContainer);
    }

    const iconMap = {
        success: 'ti-circle-check',
        error: 'ti-alert-circle',
        warning: 'ti-alert-triangle',
        info: 'ti-info-circle',
    };

    const colorMap = {
        success: 'text-success',
        error: 'text-danger',
        warning: 'text-warning',
        info: 'text-info',
    };

    const toast = document.createElement('div');
    toast.className = 'toast show';
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');

    toast.innerHTML = `
        <div class="toast-header">
            <i class="ti ${iconMap[type] || iconMap.info} ${colorMap[type] || colorMap.info} me-2"></i>
            <strong class="me-auto">${type.charAt(0).toUpperCase() + type.slice(1)}</strong>
            <button type="button" class="btn-close ms-2 mb-1" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body">
            ${message}
        </div>
    `;

    toastContainer.appendChild(toast);

    const bsToast = new bootstrap.Toast(toast, {
        delay: 3000,
        autohide: true,
    });

    bsToast.show();

    toast.addEventListener('hidden.bs.toast', () => {
        toast.remove();
    });
};

export const showConfirm = (
    title = 'Confirmation',
    message = '',
    confirmText = 'Confirm'
) => {

    return new Promise((resolve) => {

        const modalElement = document.getElementById('confirmModal');

        const titleElement = document.getElementById('confirmModalTitle');
        const messageElement = document.getElementById('confirmModalMessage');
        const confirmButton = document.getElementById('confirmModalButton');

        titleElement.textContent = title;
        messageElement.textContent = message;
        confirmButton.textContent = confirmText;

        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);

        const handleConfirm = () => {
            cleanup();
            modal.hide();
            resolve({ isConfirmed: true });
        };

        const handleClose = () => {
            cleanup();
            resolve({ isConfirmed: false });
        };

        const cleanup = () => {
            confirmButton.removeEventListener('click', handleConfirm);
            modalElement.removeEventListener('hidden.bs.modal', handleClose);
        };

        confirmButton.addEventListener('click', handleConfirm);

        modalElement.addEventListener(
            'hidden.bs.modal',
            handleClose,
            { once: true }
        );

        modal.show();
    });
};