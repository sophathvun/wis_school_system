document.querySelectorAll("[data-database-backup-delete-form]").forEach((form) => {
    form.addEventListener("submit", async (event) => {
        if (form.dataset.confirmed === "true") return;

        event.preventDefault();

        const result = window.schoolShowConfirm
            ? await window.schoolShowConfirm(
                  "Delete Database Backup",
                  "Are you sure you want to delete this database backup file?",
                  "Delete",
                  "Cancel",
              )
            : { isConfirmed: confirm("Delete this database backup?") };

        if (!result.isConfirmed) return;

        form.dataset.confirmed = "true";
        form.submit();
    });
});
