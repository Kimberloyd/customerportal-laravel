# Audit Checklist

Use this when reviewing an existing codebase for supply-chain gaps. Verify against actual config/CI files and actual code, not README claims.

## Step 1: Dependency audit

- [ ] Does any automated dependency audit run at all (native `audit` command, a dedicated scanner like Socket/Snyk, a custom script), and does it run repeatedly (CI/scheduled) rather than as a one-off?
- [ ] Does the audit cover the full resolved tree (transitive dependencies included), or only direct dependencies declared in the manifest?
- [ ] Are lifecycle/postinstall scripts across the tree identified anywhere, or does `npm install`/`composer install`/etc. run with scripts enabled and unreviewed by default?
- [ ] Is there any process (manual or automated) for reviewing a *new* dependency before it's added, or does anything get added straight from an AI suggestion / a quick search with no review?
- [ ] For a codebase with a known-suspicious dependency already installed (if investigating a specific incident): read the flagged package's actual `postinstall` script and any code that touches `process.env`/network calls, rather than assuming based on the package name alone.

## Step 2: Environment variable / secret scoping

- [ ] Is there a single, identifiable place secrets are loaded from the environment, or is `process.env`/`os.environ` read ad hoc across many files?
- [ ] Does any module clearly outside a secret's legitimate use case (a logging utility, an unrelated feature module, a third-party-facing webhook handler) have de facto access to it purely because everything shares one process-wide environment?
- [ ] Is there a documented (even informally) mapping of which secrets exist and which parts of the codebase actually need them, or is this only knowable by reading every file?
- [ ] If application-level scoping already exists, is it actually enforced (modules receive only what's passed to them) or is it cosmetic (a "scoped" object that still happens to contain everything)?

## Step 3: Lock file integrity in CI

- [ ] Does a lock file exist for the project's package manager, and is it committed (not gitignored)?
- [ ] Does CI install dependencies using the strict, lockfile-only command (`npm ci`, `pnpm install --frozen-lockfile`, `yarn install --immutable`, `pip install --require-hashes`, `composer install`) rather than a command that can silently diverge from the lock file?
- [ ] For pip specifically: does the requirements file actually have hashes at all (`--require-hashes` is a no-op/error without them)?
- [ ] Would a failed integrity check actually fail the CI job/pipeline, or could a misconfigured step (`continue-on-error`, `allow_failure`) let it proceed anyway?
- [ ] Does every path that installs dependencies for a build that reaches production go through this verified step, or is there a shortcut (a cached artifact copied between stages, a manual deploy script) that bypasses it?

## General

- [ ] If investigating a specific suspicious package: check what data it can actually reach given the current (unscoped or scoped) environment-access pattern, and what network calls it makes, rather than relying on download count or name alone to judge it.
- [ ] Present findings as a concrete, reviewable list per step (what's missing, why it matters, what fixing it involves) -- this is real infrastructure work in most codebases, not a single quick patch, and should be scoped/communicated as such.
