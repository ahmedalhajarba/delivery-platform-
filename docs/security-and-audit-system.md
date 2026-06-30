# Security and Audit System

This document explains the new platform security controls that were added.

## What was added

0. In-platform security settings page
- Admin URL: `/admin/security-settings`
- Allows configuring backup path, schedule time, retention, compression, timeout, and dump binaries.
- Values are stored in DB and loaded into runtime config automatically.
- Includes a `Run Backup Now` action to execute an immediate DB snapshot from the UI.

1. Database backup command
- Artisan command: `php artisan security:backup-db`
- Supports MySQL, PostgreSQL, and SQLite.
- Optional compression: `--compress`
- Automatic retention pruning with `--retention-days=`

2. Scheduled backup automation
- Added to scheduler in `app/Console/Kernel.php`.
- Runs daily at `SECURITY_BACKUP_SCHEDULE_TIME` (default: `02:30`).

3. Deleted record recovery
- Artisan command: `php artisan security:restore-deleted`
- Restore by model + IDs:
  - `php artisan security:restore-deleted Order 125`
  - `php artisan security:restore-deleted App\\Models\\Order 125 126`
- Restore by deletion audit row:
  - `php artisan security:restore-deleted --audit-log-id=12345`
- Restore from platform UI:
  - Open Audit Logs in admin panel.
  - Open deletion event details.
  - Click `استرجاع السجل المحذوف`.

4. Professional audit trail enrichment
- Extended audit logs with:
  - event UUID
  - action name
  - request method
  - request URL
  - user-agent
- Added structured payload details for create/update/delete/restore events.
- Added audit retention pruning via scheduler using `model:prune`.

## New configuration

New config file: `config/security.php`

Supported environment variables:

- `SECURITY_BACKUP_PATH` (default: `backups/database`)
- `SECURITY_BACKUP_RETENTION_DAYS` (default: `14`)
- `SECURITY_BACKUP_SCHEDULE_TIME` (default: `02:30`)
- `SECURITY_BACKUP_COMPRESS` (default: `true`)
- `SECURITY_BACKUP_TIMEOUT_SECONDS` (default: `300`)
- `MYSQL_DUMP_BINARY` (set absolute path if needed)
- `PG_DUMP_BINARY` (set absolute path if needed)
- `SECURITY_AUDIT_RETENTION_DAYS` (default: `365`)

## Required deployment steps

1. Run migrations

```bash
php artisan migrate
```

2. Ensure scheduler is running (Linux cron)

```bash
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
```

3. Optional (Windows/XAMPP)
- If backup command cannot find `mysqldump`, set:

```env
MYSQL_DUMP_BINARY=C:/xampp/mysql/bin/mysqldump.exe
```

## Notes

- Recovery command restores soft-deleted records. If records were hard-deleted from DB, they need a dedicated re-create workflow from snapshots.
- Backup command writes to `storage/app/backups/database` by default.
