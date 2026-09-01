# Dependency and secret security

## Automated gates

`.github/workflows/supply-chain.yml` runs on dependency/build changes and every
Monday. It blocks on npm or Composer lock inconsistencies, high-severity npm
advisories, Composer advisories, and either production Docker build failing.
Install-time npm lifecycle scripts and Composer plugins are disabled in the
production paths.

Dependabot opens weekly npm, Composer, Docker, and GitHub Actions update pull
requests. Treat those pull requests like code changes: review new maintainers,
install scripts, package adoption, and the resolved lockfile diff before merge.

## Environment boundaries

Docker Compose uses `.env` only as the source for interpolation. It does not
pass that complete file into a container.

| Service | Secret-bearing scopes |
| --- | --- |
| `app` | Database, Redis, Reverb, inventory, mail, storage, Messenger, SMS |
| `reverb` | Reverb credentials and optional Redis scaling only |
| `broadcast-worker` | Database, Redis queue, and Reverb credentials |
| `scheduler` | Database and inventory API credentials |
| `proxy`, `redis` | No application secrets |

When a service gains a responsibility, add only the required variable to that
service's allowlist in `docker-compose.yml`. Never restore a shared `env_file`.
This is process-level isolation between containers; dependencies inside the
web application process can still read the web application's own environment.

## Immutable image updates

Docker image tags remain beside SHA-256 digests for readability. During an
upgrade, pull or inspect the official image, verify its `RepoDigests`, update
the tag and digest together, then run both production builds. Never paste a
digest that has not been obtained from the registry or a verified local pull.

## Legacy password dependency retirement

Successful legacy logins are already upgraded to bcrypt. Measure what remains:

```bash
php artisan security:legacy-passwords
```

After the production count stays at zero through the agreed rollback window,
run the command with `--fail-if-present` as a final gate. Then remove
`vinsaj9/scrypt`, `LegacyPasswordHasher`, its legacy authentication branch, and
their compatibility tests in one reviewed change. Do not remove the package
while any scrypt account remains or those users will be unable to sign in.
