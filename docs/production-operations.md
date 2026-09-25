# Production operations

## Automated backups

Run the backup once daily from Synology Task Scheduler as root:

```sh
cd /volume1/docker/customerportal-laravel && BACKUP_ROOT=/volume1/docker/backups/customerportal-laravel sh scripts/backup-production.sh
```

Run verification after it and independently later in the day:

```sh
cd /volume1/docker/customerportal-laravel && BACKUP_ROOT=/volume1/docker/backups/customerportal-laravel BACKUP_MAX_AGE_HOURS=26 sh scripts/verify-latest-production-backup.sh
```

The backup covers MySQL and the private persistent storage volume, verifies checksums and archive readability, and records the deployed Git commit. Copy completed backup sets to encrypted off-NAS storage with a retention policy. Do not treat the NAS-only copy as disaster recovery.

Perform a quarterly restore drill into an isolated database/container and record the date, operator, backup timestamp, and outcome. Never restore a drill over production.

## Availability monitoring

Run the health check every five minutes from a host outside the application stack:

```sh
HEALTH_URL=https://customerportal.theomeds.com/health/ready \
ALERT_WEBHOOK_URL='https://your-alert-provider.example/webhook' \
sh scripts/check-production-health.sh
```

The script alerts only on a healthy/unhealthy transition. Store `ALERT_WEBHOOK_URL` in the scheduler's protected environment, not in Git. An external monitor is preferable because it can still alert when the NAS, tunnel, or local network is down.

## Queue isolation

Production uses separate workers for `broadcasts`, `notifications`, and `monitoring`. Each worker exits after an hour (`--max-time=3600`) and Docker restarts it, limiting long-running process memory growth. Check them with:

```sh
sudo docker compose ps broadcast-worker notification-worker monitoring-worker
sudo docker compose exec -T app php artisan queue:failed
```

After deploying the split for the first time, confirm all three services are running and `/health/ready` remains healthy for at least two scheduler intervals.

## Controlled deployment and staging

`deploy.sh` now uses a fast-forward-only pull, records images under the full Git commit, and runs container, application smoke, and public readiness checks before reporting success. To ensure the NAS deploys the commit that passed CI:

```sh
DEPLOY_COMMIT='<full-40-character-commit>' sh deploy.sh
```

Run the same command in a staging deployment first. Staging should use a separate Compose project, database, Redis volume, storage volume, Firebase project, hostname, and Cloudflare route; never point a staging environment at production data or credentials. After staging checks pass, promote the exact same commit to production.

If post-deploy checks fail, the script exits non-zero and keeps the commit-tagged images for diagnosis or rollback. Database rollback is deliberately not automatic: migrations must be backward-compatible, and reversing application code after a destructive schema change can make the incident worse. Investigate the failed check before retagging an older image.
