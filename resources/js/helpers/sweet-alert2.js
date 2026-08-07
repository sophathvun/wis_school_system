import Swal from "sweetalert2";
import "sweetalert2/dist/sweetalert2.min.css";

const alertTypes = {
    success: {
        icon: "success",
        title: "Success",
        confirmButtonColor: "#2fb344",
        timer: 3000,
    },
    error: {
        icon: "error",
        title: "Error",
        confirmButtonColor: "#d63939",
        timer: 4000,
    },
    warning: {
        icon: "warning",
        title: "Warning",
        confirmButtonColor: "#f59f00",
        timer: 4000,
    },
};

const getAlertConfig = (type) => alertTypes[type] || alertTypes.success;

const getTheme = () => {
    return (
        document.documentElement.getAttribute("data-bs-theme") ||
        window.localStorage.getItem("tabler-theme") ||
        "light"
    );
};

const getThemeOptions = () => {
    const isDark = getTheme() === "dark";

    return {
        background: isDark ? "#1f2937" : "#ffffff",
        color: isDark ? "#f8fafc" : "#1f2937",
        customClass: {
            popup: isDark ? "swal2-toast-dark" : "swal2-toast-light",
        },
    };
};

const toastOptions = () => ({
    toast: true,
    position: "top-end",
    width: "auto",
    ...getThemeOptions(),
});

export const showAlert = ({ type = "success", title, message = "" } = {}) => {
    const config = getAlertConfig(type);

    return Swal.fire({
        ...toastOptions(),
        icon: config.icon,
        title: title || config.title,
        text: message,
        showConfirmButton: false,
        timer: config.timer,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener("mouseenter", Swal.stopTimer);
            toast.addEventListener("mouseleave", Swal.resumeTimer);
        },
    });
};

export const showSuccess = (title = "Success", message = "") => {
    return showAlert({ type: "success", title, message });
};

export const showError = (title = "Error", message = "") => {
    return showAlert({ type: "error", title, message });
};

export const showWarning = (title = "Warning", message = "") => {
    return showAlert({ type: "warning", title, message });
};

export const showConfirm = (
    title = "Are you sure?",
    message = "",
    confirmText = "Yes",
    cancelText = "Cancel",
) => {
    return Swal.fire({
        ...toastOptions(),
        icon: "warning",
        title,
        text: message,
        showCancelButton: true,
        confirmButtonText: confirmText,
        cancelButtonText: cancelText,
        confirmButtonColor: alertTypes.warning.confirmButtonColor,
        cancelButtonColor: "#6c7a91",
        reverseButtons: true,
    });
};
