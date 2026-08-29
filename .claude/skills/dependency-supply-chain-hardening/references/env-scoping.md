# Scoped Environment Variable / Secret Access

## The problem concretely

In most Node/Python/PHP/Ruby applications, every module -- direct code and every transitive dependency -- runs in the same process and can read `process.env` / `os.environ` / `getenv()` directly. A module that legitimately only needs, say, a public API base URL can just as easily read `DATABASE_PASSWORD`, `STRIPE_SECRET_KEY`, or `JWT_SIGNING_KEY` -- and so can any dependency it pulls in. This is exactly the mechanism the source material describes: a compromised package doesn't need special privileges to exfiltrate every secret in the environment, ambient access is the default.

## Pattern: an explicit secrets-loading layer

Instead of code reaching for `process.env.X` wherever it's needed, centralize secret loading into one place, and pass only the specific values a given module needs into it explicitly (constructor injection, function parameters, or a scoped config object) rather than letting it reach into the global environment itself.

**Node.js example:**

```js
// secrets.js -- the ONLY place that reads process.env directly
function loadSecrets() {
  return {
    dbUrl: process.env.DATABASE_URL,
    stripeSecret: process.env.STRIPE_SECRET_KEY,
    jwtSigningKey: process.env.JWT_SIGNING_KEY,
  };
}

// billingService.js -- receives only what it needs
class BillingService {
  constructor({ stripeSecret }) {
    this.stripeSecret = stripeSecret; // does NOT have access to dbUrl or jwtSigningKey
  }
}

// composition root (app startup) -- the only place secrets.js is imported
const secrets = loadSecrets();
const billing = new BillingService({ stripeSecret: secrets.stripeSecret });
```

A dependency imported by `billingService.js` has no path to `process.env` unless it explicitly calls `process.env` itself (which is still possible in Node -- see the "limits" section below) -- but application code written this way at least doesn't hand secrets to modules that never asked for them, and makes a dependency reaching for `process.env` directly a visible, auditable anomaly rather than the invisible default.

**Python example (equivalent pattern):**

```python
# config.py -- the only module that reads os.environ
class Secrets:
    def __init__(self):
        self.db_url = os.environ["DATABASE_URL"]
        self.stripe_secret = os.environ["STRIPE_SECRET_KEY"]

# billing.py -- receives only what it needs, not the Secrets object wholesale
class BillingService:
    def __init__(self, stripe_secret: str):
        self.stripe_secret = stripe_secret
```

## The honest limit of this pattern

In a single OS process with no sandboxing, any code -- including a malicious transitive dependency -- can still call `process.env`/`os.environ` directly if it wants to; scoped injection at the application-code layer doesn't create a hard security boundary against an actively malicious dependency reading the raw environment itself. What it does do:

- Removes the *incidental* exposure where a module has access to secrets it never needed, shrinking the blast radius of a compromised legitimate-but-vulnerable dependency that gets exploited (e.g. a prototype-pollution bug in a JSON parser) rather than one that's maliciously reading env on purpose.
- Makes an explicit `process.env` read inside a random dependency's code an anomaly worth flagging in the dependency audit (Step 1), rather than indistinguishable from how every other module in the codebase already behaves.
- Is a meaningful step toward, and often paired with, stronger isolation: separate processes/containers per trust boundary, secret-manager integrations (Vault, AWS Secrets Manager, cloud KMS) that inject only per-service credentials at the infrastructure level rather than via a shared `.env` file, or runtime sandboxing (Node's experimental permission model, `NODE_OPTIONS=--experimental-permission` with `--allow-env`, though maturity/coverage of these should be verified against current docs before relying on them for real isolation guarantees).

Be explicit with the user about which level they're getting: application-level scoping (straightforward, real value, no hard guarantee against a determined malicious dependency) versus process/infra-level isolation (stronger, more work, needed if the threat model includes actively malicious code already inside the process).

## Audit signals for an existing codebase

- Application code reaches for `process.env.X` (or equivalent) scattered across many files/modules rather than through one central loading point.
- No clear list anywhere of which secrets exist and which parts of the codebase are supposed to need them.
- A module or service clearly outside the billing/payment path (e.g. a logging utility, an analytics module) has, on inspection, access to payment/auth secrets it has no legitimate use for -- purely because it's in the same process with unscoped `process.env` access.
