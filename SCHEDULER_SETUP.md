# Scheduler Setup

Use the single scheduled command below to run SLAMS background tasks:

```powershell
php spark slams:run-scheduled-tasks 24
```

## Windows Task Scheduler

Program/script:

```text
C:\laragon\bin\php\php-8.3.0-Win32-vs16-x64\php.exe
```

Add arguments:

```text
spark slams:run-scheduled-tasks 24
```

Start in:

```text
C:\laragon\www\slams
```

Recommended trigger:
- Daily
- Repeat every 1 hour

## Linux Cron

```cron
0 * * * * cd /var/www/slams && php spark slams:run-scheduled-tasks 24 >> writable/logs/scheduler.log 2>&1
```

## What It Runs
- Upcoming approved booking reminder notifications
- Upcoming approved booking reminder emails

You can extend `slams:run-scheduled-tasks` later with more background jobs.
