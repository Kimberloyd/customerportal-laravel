# Cascade Mapping

## How to build the map for a real codebase

1. **Find every table/collection with a user reference.** Grep the schema (migrations, Prisma/Sequelize/TypeORM models, Django models, Rails `belongs_to :user`, Mongoose schemas) for foreign keys or fields like `user_id`, `owner_id`, `created_by`, `author_id`, `customer_id`. Don't rely on memory of "what the app does" -- the schema is the source of truth, and forgotten tables (an old feature's leftover table, a third-party integration's local cache) are exactly what a manual guess misses.
2. **For each table, note**: what it stores, whether it's solely about this user or shared/joint (e.g. a group chat message is the user's content but also part of a conversation other users still need to see), and any legal/business reason data might need to survive the user (financial records, an active dispute, fraud investigation).
3. **Assign a fate**: cascade delete, anonymize/detach, or retain (with reason and, ideally, its own expiry).
4. **Write it down** somewhere durable -- a markdown doc in the repo, a comment block above the deletion service, a table in the project's data-privacy documentation -- not just implicit in scattered `ON DELETE` clauses across migrations. The map needs to be reviewable by someone who isn't reading every migration file.

## Worked example: a SaaS app

| Table | Contains | Fate | Reason |
|---|---|---|---|
| `users` | profile, email, auth | Anonymize (30d), then hard delete | Core PII target of the deletion |
| `sessions` / `refresh_tokens` | active auth tokens | Cascade delete immediately | No reason to retain, security risk if kept |
| `orders` | purchase history | Anonymize (keep order, detach identity) | Financial/tax records often must be retained; buyer identity doesn't need to be |
| `payments` | billing records | Anonymize, retain per finance/tax requirement | Legal retention requirement independent of the user account |
| `messages` (DMs/support) | content the user sent to another party | Anonymize sender, retain message | The other party's copy of the conversation shouldn't vanish; the identity should |
| `uploads` / `media` | files the user uploaded | Cascade delete (unless referenced elsewhere) | Personal content with no other-party interest, unless embedded in retained content |
| `support_tickets` | help desk history | Retain, anonymize requester | Support/audit trail value; PII minimization still applies |
| `audit_logs` | security/access logs | Retain (often has its own legal retention window), anonymize actor where feasible | Security/compliance value independent of the user |
| `analytics_events` | usage tracking tied to user_id | Anonymize/pseudonymize | Aggregate analytics value doesn't require identity |
| `webhooks` / third-party API keys | integration credentials | Cascade delete (revoke first) | Security -- must be actively revoked with the third party, not just deleted locally |

Every real project's table list and fate decisions will differ -- this is a template to fill in, not a universal answer. The important discipline is that *every* table gets an explicit row, including ones that turn out to be "cascade delete, no special handling" -- an unmapped table is a table nobody decided about.

## Anonymization, done properly

"Anonymize" means the record can no longer be linked back to the person, not just that the display name changed. Watch for indirect identifiers left behind: an email address still in a `billing_email` column separate from the main user record, an IP address logged alongside an "anonymized" analytics event, a support ticket's original submission still containing the user's full name in a free-text field. A thorough anonymization pass checks every column of every retained table for anything PII-shaped, not just the obvious `name`/`email` fields on the primary user row.
