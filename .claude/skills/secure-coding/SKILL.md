---
name: secure-coding
description: >
  Apply security best practices and principles to JavaScript/TypeScript and PHP code.
  Use this skill whenever the user shares code to review, asks how to write a feature securely,
  mentions security concerns, asks about vulnerabilities, wants to fix insecure code, or uses
  words like "secure", "safe", "vulnerability", "injection", "XSS", "CSRF", "auth", "sanitize",
  "validate", "encrypt", "hash", or "permission". Also trigger proactively when the user writes
  code that handles user input, authentication, file uploads, database queries, API endpoints,
  passwords, tokens, or sensitive data — even if they don't mention security explicitly.
  Covers all skill levels: explains concepts simply for beginners, goes deep for senior devs.
---

# Secure Coding Skill

A skill for reviewing, writing, and fixing JavaScript/TypeScript and PHP code with security best practices baked in. Adapts explanations to the user's level — simple and clear for beginners, detailed and technical for experienced devs.

---

## Core Responsibilities

1. **Review code for vulnerabilities** — scan for common and subtle security issues
2. **Write secure code from scratch** — apply security principles by default when generating new code
3. **Explain security principles** — teach the "why" behind each recommendation
4. **Suggest fixes** — always provide a corrected, secure version of insecure code

---

## Workflow

### When reviewing existing code:
1. Identify all vulnerabilities (see checklists below)
2. Explain each issue clearly — what it is, why it's dangerous, and how an attacker could exploit it
3. Provide a fixed, secure version of the code
4. Summarize with a severity rating: 🔴 Critical / 🟠 High / 🟡 Medium / 🟢 Low

### When writing new code:
1. Apply all relevant principles from the checklists below by default
2. Add inline comments explaining security decisions (e.g., `// Parameterized query prevents SQL injection`)
3. If the user asks for something that has a secure and insecure way, always use the secure way and explain why

### When explaining concepts:
1. Give a plain-language summary first
2. Show a vulnerable example, then a fixed example
3. Link the concept to a real-world attack scenario

---

## Security Checklists

### JavaScript / TypeScript

#### Input & Output
- [ ] Never trust user input — always validate and sanitize
- [ ] Use allowlists (not blocklists) for input validation
- [ ] Escape output before rendering to DOM (`textContent` over `innerHTML`)
- [ ] Avoid `eval()`, `Function()`, `setTimeout(string)`, `setInterval(string)`
- [ ] Use `DOMPurify` or equivalent when rendering HTML from user input

#### Authentication & Authorization
- [ ] Use `httpOnly` and `Secure` flags on cookies
- [ ] Implement CSRF protection (SameSite cookies, CSRF tokens)
- [ ] Never store sensitive data in `localStorage` (use memory or `httpOnly` cookies)
- [ ] Use short-lived JWTs; validate `alg`, `exp`, and `iss` fields
- [ ] Apply rate limiting on login and sensitive endpoints

#### Dependencies & Environment
- [ ] Audit dependencies regularly (`npm audit`)
- [ ] Pin dependency versions; avoid `*` or `latest`
- [ ] Never commit secrets — use environment variables
- [ ] Use `Content-Security-Policy` headers
- [ ] Enable `Strict-Transport-Security` (HSTS)

#### Node.js / Server-side
- [ ] Use parameterized queries / ORMs for database access
- [ ] Validate and sanitize all request parameters (body, query, headers)
- [ ] Limit file upload types, sizes, and storage paths
- [ ] Use `helmet` middleware for Express apps
- [ ] Avoid exposing stack traces in production errors

---

### PHP

#### Input & Output
- [ ] Never use raw `$_GET`, `$_POST`, `$_REQUEST` in queries or output
- [ ] Use `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')` before echoing to HTML
- [ ] Validate types and ranges with `filter_var()` and `filter_input()`
- [ ] Avoid `include`/`require` with user-supplied paths (LFI/RFI)
- [ ] Disable `allow_url_include` in `php.ini`

#### Database
- [ ] Always use PDO or MySQLi with **prepared statements and bound parameters**
- [ ] Never concatenate user input into SQL strings
- [ ] Use least-privilege DB accounts (no root)
- [ ] Avoid exposing DB errors to users — log them server-side

#### Authentication & Sessions
- [ ] Use `password_hash()` (bcrypt) and `password_verify()` — never MD5/SHA1 for passwords
- [ ] Regenerate session ID after login: `session_regenerate_id(true)`
- [ ] Set `session.cookie_httponly = 1` and `session.cookie_secure = 1` in `php.ini`
- [ ] Use CSRF tokens for all state-changing forms
- [ ] Implement account lockout / rate limiting on login

#### File Handling
- [ ] Validate file types by MIME type and extension allowlist (not just extension)
- [ ] Store uploaded files outside webroot or use randomized names
- [ ] Disable PHP execution in upload directories (`.htaccess`: `php_flag engine off`)

#### Configuration
- [ ] Set `display_errors = Off` in production
- [ ] Use `error_log` to log errors server-side
- [ ] Set `expose_php = Off`
- [ ] Keep PHP and all packages updated

---

## OWASP Top 10 Quick Reference

| # | Vulnerability | JS/TS Risk | PHP Risk |
|---|---------------|-----------|---------|
| A01 | Broken Access Control | API route guards | Missing auth checks |
| A02 | Cryptographic Failures | Weak JWT, plain passwords | MD5 passwords, plain DB |
| A03 | Injection (SQL/XSS/etc) | `innerHTML`, `eval()` | Raw `$_POST` in queries |
| A04 | Insecure Design | No rate limiting | No CSRF, no validation |
| A05 | Security Misconfiguration | Missing headers | `display_errors=On` |
| A06 | Vulnerable Components | Outdated npm packages | Outdated Composer packages |
| A07 | Auth Failures | Weak JWT, no MFA | Weak sessions |
| A08 | Software Integrity | No lockfile, CDN abuse | No Composer lock |
| A09 | Logging Failures | No audit logs | Errors exposed to user |
| A10 | SSRF | Unvalidated fetch() | `file_get_contents()` abuse |

---

## Tone & Communication Guide

| Audience Signal | How to Respond |
|----------------|----------------|
| Beginner (simple code, asks "what is X") | Use analogies, avoid jargon, explain attacks in plain English |
| Intermediate (knows frameworks, asks "how to fix") | Skip basics, focus on the fix and why it works |
| Senior (uses security terms, asks edge cases) | Go deep — discuss threat models, CVEs, tradeoffs |

When unsure, briefly explain the concept AND provide the technical fix. The reader can skip what they already know.

---

## Example Outputs

### Vulnerability Review Format
```
🔴 CRITICAL — SQL Injection (Line 12)
Issue: User input is concatenated directly into the SQL query.
Risk: An attacker can read, modify, or delete your entire database.
Fix: Use a prepared statement (see below).
```

### Secure Code Comment Style
```js
// ✅ Parameterized query — prevents SQL injection
const [rows] = await db.execute('SELECT * FROM users WHERE email = ?', [email]);
```

---

## Reference Files

- `references/js-examples.md` — Vulnerable vs. secure JS/TS code examples
- `references/php-examples.md` — Vulnerable vs. secure PHP code examples

Read the relevant reference file when you need concrete code examples to show the user.
