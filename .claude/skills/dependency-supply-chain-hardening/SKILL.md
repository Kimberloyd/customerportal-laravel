---
name: dependency-supply-chain-hardening
description: "Implements and audits software supply-chain security across npm/yarn/pnpm, pip, Composer, and other package ecosystems using a 3-part pattern: (1) a full dependency-tree audit -- direct and transitive -- flagging low-download packages, recently-changed maintainers, and postinstall/lifecycle scripts that execute on install; (2) scoped environment-variable access so each module gets only the secrets it needs, not the full process environment, since a compromised dependency with blanket env access can exfiltrate every credential; (3) lock file integrity verification in CI that blocks a build/deploy when checksums don't match. Use whenever asked to audit dependencies, review supply-chain security, harden a CI pipeline against malicious packages, review a package before installing it, or lock down env-var/secret exposure across modules. Also trigger if a codebase has no lock-file integrity check in CI, or the user mentions a typosquatted package, a postinstall script, or credentials being exfiltrated."
---

# Dependency Supply-Chain Hardening

A dependency is code you didn't write, running with whatever access your application gives it. A typosquatted package one character off from a legitimate one, sitting at 50,000 weekly downloads and reading `process.env` on every startup to exfiltrate database credentials, API keys, and signing secrets to an external server, is not a hypothetical -- it's the shape of a real, common attack, and it works precisely because most codebases trust their dependency list by default and give every module blanket access to every secret.

Three concrete protections close most of this gap: know what's actually in the dependency tree and flag what looks wrong, stop handing every module every secret, and make sure a tampered lock file can't slip through CI unnoticed.

## Step 1: The dependency-tree audit

Audit the *full* tree, not just what's declared in the manifest -- a modern application can pull in hundreds of transitive dependencies from maintainers nobody on the team has ever heard of, and a compromised package rarely shows up as a direct dependency.

Flag, for every package in the lock file (direct and transitive):
- **Low adoption**: unusually few weekly downloads relative to how deeply it's depended on -- a widely-required package with a tiny download count is a red flag (small legitimate niche packages exist too, so this is a signal to review, not an automatic reject).
- **Recent maintainer/ownership changes**: a package whose publishing maintainer changed recently is a classic account-takeover or malicious-handoff pattern -- flag anything that changed within roughly the last 90 days for review.
- **Lifecycle scripts that execute on install** (`postinstall`, `preinstall`, npm's `install` script, pip's custom `setup.py` build steps, Composer's `scripts.post-install-cmd`): these run arbitrary code the moment the package is installed, before anyone has read a line of its source. Not automatically malicious, but exactly the mechanism real supply-chain attacks use, so every one of them warrants a look at what it actually does.
- **Name similarity to popular packages** (typosquatting): a package name one character or transposition off from a well-known package is a deliberate deception pattern.

See `references/dependency-audit.md` for concrete audit tooling and commands across npm/yarn/pnpm, pip, and Composer, and how to script the tree-wide check rather than eyeballing a handful of direct dependencies.

## Step 2: Scoped environment-variable / secret access

Most applications hand every module the same thing: the full `process.env` (or `os.environ`, or equivalent) with every secret the application holds, regardless of whether that module has any legitimate reason to see the database password, the Stripe secret, or the JWT signing key. That means a single compromised dependency -- anywhere in the tree, not just a direct one -- has read access to everything.

Direct instead: each module/service receives only the specific environment variables it actually needs, passed explicitly rather than inherited implicitly from a shared global object. See `references/env-scoping.md` for concrete patterns (a secrets-loading layer that hands out narrow, named subsets; dependency injection instead of ambient global access; per-service secret managers in more mature setups).

This is a real architecture change, not a one-line fix -- flag it clearly as such when auditing a codebase that has none of this, since retrofitting scoped access into a codebase built around ambient `process.env` reads everywhere is nontrivial work.

## Step 3: Lock file integrity verification in CI

A lock file (`package-lock.json`/`yarn.lock`/`pnpm-lock.yaml`, `requirements.txt` with hashes or a `poetry.lock`/`Pipfile.lock`, `composer.lock`) pins exact versions and, ideally, checksums. If a dependency is modified upstream after installation (a compromised registry, a hijacked package version, a supply-chain attack that republishes over an existing version), the checksum stops matching what was originally pinned.

CI should verify this on every build and **reject the build** on any mismatch -- not just warn. See `references/ci-lockfile-verification.md` for the actual commands/config (`npm ci` with an integrity check, `pip install --require-hashes`, Composer's `--no-scripts` + lock validation, and how to wire each into a CI pipeline as a hard gate rather than an advisory step).

## Auditing an existing codebase

Work through `references/audit-checklist.md`: does a dependency audit run anywhere (CI, a pre-commit hook, a scheduled job) or is it purely ad hoc/manual; does every module actually get unscoped access to the full environment right now; does CI actually fail the build on a lock file mismatch, or does the lock file exist without anything checking it. A codebase that "has a lock file" is not the same as a codebase that verifies it.

## Verification

After implementing: actually trigger each check. Deliberately edit a lock file's checksum (in a throwaway branch/test) and confirm CI rejects it. Confirm a module without a granted scope genuinely cannot read an unrelated secret, not just that the code "should" restrict it. Run the dependency audit against the real lock file and review what it actually flags, rather than assuming the tooling is correctly configured from reading the config alone.
