# Cron Entries

The framework does **not** schedule jobs internally. Pruning and other periodic
maintenance is invoked by an OS-side cron entry (or Windows Task Scheduler for
local development) calling the project's `cli` entrypoint.

## Required entries

Add these to your production crontab (`crontab -e` as the application user):

```cron
# Cache pruning — every night at 3 AM
0 3 * * * cd /var/www/html && php cli cache:prune >> /var/log/lexicon/cache-prune.log 2>&1

# Notification pruning — every night at 3:05 AM (after cache, to avoid I/O overlap)
5 3 * * * cd /var/www/html && php cli notifications:prune >> /var/log/lexicon/notifications-prune.log 2>&1
```

Adjust the project path and PHP binary location as appropriate for your host.

## Retention policy

`notifications:prune` enforces:
- Read notifications older than **30 days** are hard-deleted.
- Any notification older than **90 days** is hard-deleted (read or unread).

There is no soft-delete column on `notifications` — pruning is a `DELETE`. Run
order does not matter (the two passes are independent).

## Windows / local development

On Windows (Laragon) use Task Scheduler:

1. Open Task Scheduler → Create Basic Task.
2. Trigger: Daily, 3:05 AM.
3. Action: Start a program.
4. Program: `C:\laragon\bin\php\php-8.3.29-nts-Win32-vs16-x64\php.exe`
5. Arguments: `cli notifications:prune`
6. Start in: `C:\laragon\www\lexicon`

A manual invocation is always safe:

```bash
php cli notifications:prune
```
