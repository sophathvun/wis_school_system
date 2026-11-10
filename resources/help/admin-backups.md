## Before you start

Open **Administrator → Database Backups**. Access is available to super administrators, access-settings managers, or users with the appropriate backup permissions. Creating, downloading, and deleting have their own permission checks.

Database backup files contain sensitive school data. Keep downloaded copies in an approved secure location.

## Review storage

Check the storage information, existing backup count, dates, and file sizes. Confirm that sufficient storage is available before creating a new backup.

## Create a backup

1. Choose **Backup Now**.
2. Wait for the outcome message.
3. Review the newly created SQL file in the backup list.
4. Check its filename, timestamp, and size.

If backup creation fails, read the displayed error and ask the server administrator to investigate it. Do not assume that clicking Backup Now created a usable file without checking the result.

## Download a copy

Use **Download** for the intended backup. Keep an approved copy on another secure computer or drive, as the page recommends. A server-only copy may be unavailable if the server itself fails.

An SQL database backup should not be assumed to include every uploaded student photo, certificate file, or server configuration. Coordinate a complete recovery plan with the server administrator when those files are also required.

## Delete an old backup

Verify the file and confirm that the retention plan allows deletion before using **Delete**. This removes the selected backup file; it is not an operation for deleting current student records.

## Restore planning

The current page provides creation, download, and deletion actions. It does not provide a restore wizard. Restoration should be planned and performed by the authorized server administrator with a verified backup and an appropriate recovery procedure.

No backup is created or restored by opening this guide.
