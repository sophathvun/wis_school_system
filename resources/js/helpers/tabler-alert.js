const alertTypes = {
    success: {
        title: "Success",
        icon: "ti-circle-check",
        iconColor: "text-green",
        statusColor: "bg-success",
        buttonColor: "btn-success",
    },
    error: {
        title: "Error",
        icon: "ti-alert-circle",
        iconColor: "text-red",
        statusColor: "bg-danger",
        buttonColor: "btn-danger",
    },
    warning: {
        title: "Warning",
        icon: "ti-alert-triangle",
        iconColor: "text-yellow",
        statusColor: "bg-warning",
        buttonColor: "btn-warning",
    },
    confirm: {
        title: "Confirmation",
        icon: "ti-help-circle",
        iconColor: "text-red",
        statusColor: "bg-danger",
        buttonColor: "btn-danger",
    },
};

const getAlertConfig = (type) => alertTypes[type] || alertTypes.success;

const createAlertModal = ({
    type = "success",
    title,
    message = "",
    confirmText = "OK",
    cancelText = "Cancel",
    showCancel = false,
}) => {
    const config = getAlertConfig(type);
    const modalElement = document.createElement("div");

    modalElement.className = "modal modal-blur fade";
    modalElement.tabIndex = -1;
    modalElement.setAttribute("aria-hidden", "true");

    modalElement.innerHTML = `
        <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
            <div class="modal-content">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                <div class="modal-status ${config.statusColor}"></div>
                <div class="modal-body text-center py-4">
                    <i class="ti ${config.icon} icon mb-2 ${config.iconColor} icon-lg"></i>
                    <h3 data-alert-title></h3>
                    <div class="text-secondary" data-alert-message></div>
                </div>
                <div class="modal-footer">
                    <div class="w-100">
                        <div class="row g-2" data-alert-actions></div>
                    </div>
                </div>
            </div>
        </div>
    `;

    modalElement.querySelector("[data-alert-title]").textContent =
        title || config.title;
    modalElement.querySelector("[data-alert-message]").textContent = message;

    const actions = modalElement.querySelector("[data-alert-actions]");

    if (showCancel) {
        const cancelColumn = document.createElement("div");
        cancelColumn.className = "col";
        cancelColumn.innerHTML = `
            <button type="button" class="btn w-100" data-bs-dismiss="modal" data-alert-cancel></button>
        `;
        cancelColumn.querySelector("[data-alert-cancel]").textContent =
            cancelText;
        actions.appendChild(cancelColumn);
    }

    const confirmColumn = document.createElement("div");
    confirmColumn.className = "col";
    confirmColumn.innerHTML = `
        <button type="button" class="btn ${config.buttonColor} w-100" data-alert-confirm></button>
    `;
    confirmColumn.querySelector("[data-alert-confirm]").textContent =
        confirmText;
    actions.appendChild(confirmColumn);

    document.body.appendChild(modalElement);

    return modalElement;
};

export const showAlert = ({
    type = "success",
    title,
    message = "",
    confirmText = "OK",
    cancelText = "Cancel",
    showCancel = false,
} = {}) => {
    return new Promise((resolve) => {
        const modalElement = createAlertModal({
            type,
            title,
            message,
            confirmText,
            cancelText,
            showCancel,
        });
        const confirmButton = modalElement.querySelector(
            "[data-alert-confirm]",
        );
        const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        let isResolved = false;

        const resolveOnce = (result) => {
            if (isResolved) return;

            isResolved = true;
            resolve(result);
        };

        const handleConfirm = () => {
            resolveOnce({ isConfirmed: true });
            modal.hide();
        };

        const handleHidden = () => {
            resolveOnce({ isConfirmed: false });
            modal.dispose();
            modalElement.remove();
        };

        confirmButton.addEventListener("click", handleConfirm);
        modalElement.addEventListener("hidden.bs.modal", handleHidden, {
            once: true,
        });

        modal.show();
    });
};

export const showSuccess = (title = "Success", message = "") => {
    return showAlert({ type: "success", title, message, confirmText: "OK" });
};

export const showError = (title = "Error", message = "") => {
    return showAlert({ type: "error", title, message, confirmText: "OK" });
};

export const showWarning = (title = "Warning", message = "") => {
    return showAlert({ type: "warning", title, message, confirmText: "OK" });
};

export const showConfirm = (
    title = "Confirmation",
    message = "",
    confirmText = "Confirm",
    cancelText = "Cancel",
) => {
    return showAlert({
        type: "confirm",
        title,
        message,
        confirmText,
        cancelText,
        showCancel: true,
    });
};

export const showToast = (type = "success", message = "") => {
    const config = getAlertConfig(type);

    return showAlert({
        type,
        title: config.title,
        message,
        confirmText: "OK",
    });
};
