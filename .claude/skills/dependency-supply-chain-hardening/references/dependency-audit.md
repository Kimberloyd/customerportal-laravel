# Dependency Tree Audit

## npm / yarn / pnpm

- `npm audit` / `pnpm audit` / `yarn audit` catch *known* vulnerabilities, but won't catch a brand-new malicious package with no CVE yet -- treat this as a baseline, not the whole audit.
- List the full resolved tree, not just direct deps: `npm ls --all` or read `package-lock.json` directly (`packages` object in lockfile v2/v3 covers every resolved package, transitive included).
- Flag packages with lifecycle scripts: search the lockfile / `node_modules/*/package.json` for `scripts.postinstall`, `scripts.preinstall`, `scripts.install`. `npm install --ignore-scripts` (or pnpm's default-deny lifecycle-script policy) prevents them from running until reviewed -- a strong default for CI and for any environment where unreviewed installs happen.
- Low weekly downloads / recent maintainer changes require querying the registry: `npm view <package> --json` returns `maintainers` (compare against what's expected) but not directly a "maintainer changed on date X" field or download counts -- pull weekly downloads via the npm downloads API (`https://api.npmjs.org/downloads/point/last-week/<package>`) and treat a sudden drop in an otherwise-established package, or a very low count for something with many dependents, as a signal to review manually.
- For typosquatting: compare each new/changed dependency name against the list of popular packages in the same category using a simple edit-distance check (Levenshtein distance 1-2 from a well-known package name is worth a manual look) before adding it.
- Tools like `socket.dev`'s CLI/GitHub App, `npm-audit-resolver`, or Snyk's dependency scanning automate a lot of this (lifecycle-script detection, maintainer-change alerts, typosquat detection) and are worth wiring into CI rather than building the whole thing from scratch -- but understand what they check so gaps in coverage (e.g. a tool that only checks direct deps) are visible.

## pip / Python

- `pip list --format=freeze` plus `pipdeptree` shows the full resolved tree including transitive dependencies.
- `pip-audit` checks for known vulnerabilities (PyPI Advisory Database) -- same caveat as `npm audit`, it's a baseline not a full audit.
- Lifecycle-script equivalent: a package's `setup.py` can execute arbitrary code at install time (unlike a pure `pyproject.toml`/wheel install, which is safer). Prefer wheels over sdists where possible, and review any package that still ships/requires a `setup.py` build step for unfamiliar or newly-added dependencies.
- Maintainer/ownership signals are visible on PyPI's package page (`https://pypi.org/project/<name>/#history` for version history, project page for maintainer list) -- no clean single API for "maintainer changed on date X" equivalent to npm, so this needs periodic manual review for security-sensitive dependencies, or reliance on third-party supply-chain tools (Socket, etc.) that track this.

## Composer / PHP

- `composer show --tree` or `composer show -a` for the full dependency tree.
- Lifecycle-script equivalent: `scripts.post-install-cmd` / `pre-install-cmd` in a package's `composer.json` run on install -- `composer install --no-scripts` (or the equivalent CI flag) skips these until reviewed, same principle as npm's `--ignore-scripts`.
- `composer audit` checks known vulnerabilities against the FriendsOfPHP advisory database.
- Packagist doesn't expose a simple maintainer-change API either -- same caveat as pip; rely on periodic review or a third-party scanner for this specific signal.

## Making this a repeatable check, not a one-time read-through

The audit only has value if it runs regularly, not just once when this skill is invoked. Wire whatever's available (native `audit` command, a dedicated tool like Socket/Snyk, or a custom script combining the above) into CI (fails or warns on new findings) and/or a scheduled job (weekly re-check of the existing tree, since a package can turn malicious after it was already installed and trusted). A one-time audit only catches the state of the tree at that moment.

## What to actually do with a flagged package

Flag ≠ auto-remove. A flagged package needs human review: read its source (or at least its lifecycle scripts and any network calls) before deciding to keep, pin to a known-good version, or replace it. Present findings as a reviewable list (package name, what was flagged, why) rather than either silently ignoring flags or automatically ripping out dependencies that might be legitimate.
