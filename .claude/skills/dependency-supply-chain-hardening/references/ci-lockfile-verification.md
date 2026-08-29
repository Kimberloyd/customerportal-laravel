# Lock File Integrity Verification in CI

## The goal

A lock file pins exact resolved versions (and, for most modern package managers, integrity hashes/checksums). If a package's published contents change after being pinned -- a compromised registry, a maintainer account takeover republishing over an existing version, a supply-chain attack -- installing from the lock file should either fail outright or silently install compromised code, depending on whether the install step actually verifies integrity. CI must be the place this gets caught, blocking the build/deploy rather than merely warning.

## npm

`npm ci` (not `npm install`) is the correct CI command specifically because it installs strictly from `package-lock.json` and verifies each package's integrity hash from the lockfile against what's actually downloaded, failing the install on a mismatch. Using `npm install` in CI instead of `npm ci` is a common gap -- `npm install` can silently update the lockfile and doesn't enforce the same strict verification.

```yaml
# CI step
- run: npm ci   # NOT npm install
```

Combine with `--ignore-scripts` in contexts where lifecycle scripts haven't been reviewed (see `dependency-audit.md`), and re-enable scripts selectively only for packages that have been vetted, if needed.

## Yarn

Yarn Classic: `yarn install --frozen-lockfile` fails if the lockfile would need to change (i.e. it's out of sync with `package.json`), and Yarn verifies checksums during install by default.

Yarn Berry (2+): `yarn install --immutable` is the equivalent strict mode; add `--immutable-cache` to also verify the local cache hasn't been tampered with.

## pnpm

`pnpm install --frozen-lockfile` is the CI-appropriate strict mode, failing if the lockfile is out of date, and pnpm verifies integrity hashes from `pnpm-lock.yaml` during install.

## pip / Python

`pip install --require-hashes -r requirements.txt` requires every package in the requirements file to have a hash specified and verifies it on install, failing otherwise. This requires the requirements file to actually be generated with hashes (`pip-compile --generate-hashes` via pip-tools, or `poetry export --with-hashes` from a Poetry lockfile). A `requirements.txt` without hashes gives no integrity verification regardless of CI configuration -- check this first, since it's a common gap (many `requirements.txt` files pin versions but not hashes).

Poetry: `poetry install` verifies against `poetry.lock`'s hashes by default; ensure CI runs `poetry install` against a committed lockfile rather than `poetry update` (which would resolve fresh versions, defeating the purpose).

## Composer / PHP

`composer install` (not `composer update`) in CI installs strictly from `composer.lock`, and Composer verifies package integrity via the lock file's recorded hashes. Add `--no-scripts` if lifecycle scripts haven't been reviewed, and consider `composer validate --strict` as an additional check that the lock file is actually in sync with `composer.json`.

## The CI gate itself

Whatever the ecosystem, the check needs to be a blocking step, not advisory:

```yaml
jobs:
  build:
    steps:
      - uses: actions/checkout@v4
      - run: npm ci   # fails the whole job on integrity mismatch
      - run: npm run build
      # subsequent steps only run if the install step succeeded
```

Confirm the pipeline is actually configured so a failed install step fails the whole job/pipeline (most CI systems do this by default for a non-zero exit code, but a misconfigured pipeline with `continue-on-error: true` or equivalent on the install step would silently proceed past a checksum mismatch -- check for this specifically when auditing an existing pipeline).

## Audit signals for an existing pipeline

- CI uses `npm install`/`composer update`/unlocked pip installs instead of the strict, lockfile-only equivalents.
- A `requirements.txt` has no hashes at all, so `--require-hashes` isn't even possible without regenerating it.
- The install step exists but a pipeline-level setting (`continue-on-error`, `allow_failure`, etc.) would let the build proceed even if it failed.
- No CI step touches dependency installation at all -- e.g. a deploy pipeline that just copies a pre-built artifact/`node_modules` from a prior stage without ever having verified it there either.
