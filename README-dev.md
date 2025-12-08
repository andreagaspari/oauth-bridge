# OAuthProxyBridge — Developer Guide

This repository ("oauth-bridge") implements a small, secure OAuth 2.0 proxy that centralizes OAuth operations for client sites (for example WordPress themes/plugins). It is organized as a lightweight PHP micro-framework (router, controllers, middleware, services, models) and is intended to be easy to run on shared hosting.

Purpose of this document
 - Provide developers (and LLM-based assistants) with everything required to understand, extend and maintain the project.
 - Explain core flows, routes, DB schema, security rules and coding conventions.
 - Translate any non-English content and keep the documentation aligned with the current code state.

Note about `@since` values
 - The canonical `@since` version to use in DocBlocks is the version found in the `index.php` header (the `@version` tag). Do not hardcode a value here; read `index.php` when adding public API DocBlocks so `@since` tracks the release/version in the codebase.



Table of contents
 - [Architecture and high-level flows](#architecture-and-high-level-flows)
 - [Quick development setup](#quick-development-setup)
 - [Folder layout and responsibilities](#folder-layout-and-responsibilities)
 - [Core routes and payloads](#core-routes-and-payloads)
 - [Database schema essentials](#database-schema-essentials)
 - [One-time token / transient mechanism (why and how)](#one-time-token--transient-mechanism-why-and-how)
 - [Security, logging and redaction rules](#security-logging-and-redaction-rules)
 - [How to add a provider](#how-to-add-a-provider)
 - [DocBlock and coding conventions](#docblock-and-coding-conventions)
 - [Operational checklist and maintenance](#operational-checklist-and-maintenance)
 - [Practical additions (examples, migrations, CI, troubleshooting)](#practical-additions-examples-migrations-ci-troubleshooting)
   - [Integration example (WordPress)](#integration-example--wordpress-server-side)
   - [Request / response concrete examples](#request--response-concrete-examples)
   - [SQL migration snippet](#sql-migration-snippet)
   - [Error codes & client actions](#error-codes-and-client-actions)
   - [Redirect URI validation](#redirect-uri-validation-algorithm)
   - [Logging examples & retention](#logging-format-and-redaction-example)
   - [Composer scripts](#composer-scripts-recommended-additions-to-composerjson)
   - [CI skeleton](#minimal-github-actions-ci-skeleton)
   - [Troubleshooting commands](#useful-curl-commands-for-troubleshooting)
   - [LLM prompt templates](#llm-prompt-templates-safe-change-requests)
 - [Security operational runbook](#security-operational-runbook)
 - [DocBlock automation (extract `@version`)](#docblock-automation-extract-version-from-indexphp)

---

Architecture and high-level flows
--------------------------------
The bridge is a trusted central OAuth client. Client sites register a `site_key` (server-to-server API key) and ask the Bridge to perform an OAuth flow on their behalf. This avoids distributing provider client credentials across multiple client installations.

Supported flows (summary):
 - Server-to-server start (recommended): client backend calls `POST /auth/{provider}/get-start-token` with `site` and `oauth_bridge_api_key` to obtain a one-time start token, then redirects the browser to `/auth/{provider}/start-with-token?token=...`.
 - Browser-consume: Bridge consumes the start token, stores `state` and `redirect_uri` in session, redirects user to the provider consent screen; on callback Bridge exchanges `code`→tokens and forwards tokens to client via POST (auto-submit HTML form) or redirects to the client with `?oauth_error=...` on errors.
 - Refresh: `POST /auth/{provider}/refresh` exchanges a `refresh_token` for a new `access_token` (server-side only).

Always prefer the server-to-server start token flow to avoid cross-site cookie SameSite issues.

Quick development setup
------------------------
Minimal local setup steps:

```bash
git clone https://github.com/andreagaspari/oauth-bridge.git oauth-bridge
cd oauth-bridge
composer install
cp example.env .env   # edit DB and provider credentials
php -S 127.0.0.1:8000 dev-router.php
```

Database:
 - Create a database and import `config/schema.sql`.

Post-install checks:
 - `GET /ping` should return `OK`
 - Visit `/admin` and create a `site_key` for a client site

Folder layout and responsibilities
----------------------------------
Important folders and files (short):

 - `config/` — runtime configuration and `schema.sql`
 - `src/Controllers/` — `OAuthController`, `AdminController`, `ApiController`
 - `src/Core/` — `Router`, `Request`, `Response`, `Session`, `Assets`, `Svg`
 - `src/Middleware/` — `ApiKeyMiddleware`, `LogMiddleware`, `CsrfMiddleware`, `RateLimitMiddleware`
 - `src/Models/` — `SiteKey`, `Log`, `User`
 - `src/Services/` — `ServiceInterface`, `ServiceManager`, provider services (e.g. `GoogleService`)
 - `routes.php` — route registration
 - `bootstrap.php` — app bootstrap (autoload, .env, middleware)

Core routes and payloads
-------------------------
Canonical routes and their purpose:

 - `POST /auth/{provider}/get-start-token`  → create start token
	 - Payload: `{ site: string, oauth_bridge_api_key: string, client_wpnonce?: string, redirect_uri?: string }`
	 - Validates `site` + `oauth_bridge_api_key`, inserts a row into `oauth_start_tokens`, returns `{ ok: true, token: string }`.

 - `GET /auth/{provider}/start-with-token?token=...` → consume token in browser
	 - Consumes `oauth_start_tokens.token` (one-time), sets session `oauth2state` and `redirect_uri`, redirects to provider auth URL.

 - `GET /callback` → provider callback
	 - Exchanges `code` for tokens, logs outcome, then either POSTs tokens to client `redirect_uri` (auto-submit) or redirects to `redirect_uri?oauth_error=...` on failure.

 - `POST /auth/{provider}/refresh` → server-side refresh exchange
	 - Requires server-to-server auth (`oauth_bridge_api_key`) and a valid `refresh_token`.

Database schema essentials
-------------------------
See `config/schema.sql` for the full schema. Key tables used by integration:

 - `site_keys` — registered clients: `site`, `api_key`, optional metadata (allowed redirect hosts)
 - `oauth_start_tokens` — ephemeral start tokens for the browser-consume flow; columns: `token` (PK), `site`, `provider`, `state`, `client_wpnonce` (nullable), `redirect_uri` (nullable), `created_at`, `expires_at`, `used`
 - `logs` — request and domain events. Sensitive values are redacted before saving.

One-time token / transient mechanism (why and how)
-------------------------------------------------
Problem: modern browsers and SameSite cookie rules can prevent the client site's cookies (and therefore WordPress nonces) from being available to the Bridge during provider callbacks. That causes nonce/state verification failures.

Solution implemented:
 - When the client backend initiates the flow it requests a one-time token from the Bridge and may provide the client WP nonce (`client_wpnonce`) and a `redirect_uri`.
 - Bridge stores that information in `oauth_start_tokens` with a short TTL (recommended 5 minutes) and returns `token` to the client backend.
 - The client backend redirects the browser to the Bridge consumer URL with the `token` query param.
 - Bridge consumes the token (marking `used=1`), restores `client_wpnonce` into session and proceeds with the provider flow. On callback the Bridge can include the restored `client_wpnonce` in the POST to the client so the client can validate it without relying on cross-site cookies.

Rules and recommendations:
 - TTL must be short (5 minutes suggested).
 - Always mark tokens as used after consumption to prevent replay.
 - For user-cancel or provider error paths the Bridge redirects to `redirect_uri?oauth_error=...` — the client must display the error. The GET error path intentionally does not require transient validation.

Security, logging and redaction rules
-----------------------------------
 - Authenticate all server-to-server calls with `oauth_bridge_api_key`. Never expose this key in client-side JS.
 - Logs must never contain full secrets. Redact API keys/tokens — store only truncated metadata (e.g. `api_key_last6`, `token_last6`).
 - Validate `redirect_uri`: accept relative paths or absolute URLs where the host matches the registered `site`. Reject third-party redirect hosts.
 - Use HTTPS in production, and rotate keys on compromise.

How to add a new provider
--------------------------
Steps:
 1. Implement a `Service` class under `src/Services/` implementing `ServiceInterface` (methods: `getAuthUrl`, `exchangeCode`, `refreshToken`, optionally `getProfile`).
 2. Add provider config to `config/providers.php` (client id/secret, endpoints, default scopes).
 3. Register the provider in `ServiceManager` so `OAuthController` can instantiate it by provider name.
 4. Add any provider-specific route bindings if needed.

DocBlock and coding conventions
------------------------------
 - Follow PSR-12 formatting.
 - Use `php-cs-fixer` or equivalent in CI to enforce style.
 - DocBlock requirements (every public class/method): include `@package`, `@author`, `@since`, `@param`, `@return`.
 - For `@since` use the version present in `index.php` (`@version` in the file header) when documenting public APIs.

#DocBlock automation — extract `@version` from `index.php`
You can extract the canonical `@version` value programmatically when generating DocBlocks. Example commands:

```bash
# grep + PCRE (prints the token after @version)
grep -Po '(?<=@version\s)[^\s]+' index.php || true

# PHP one-liner that prints the @version value (if present)
php -r "echo preg_match('/@version\s+([^\s]+)/', file_get_contents('index.php'), \$m) ? \$m[1] : '';"
```

Use these commands in tooling to populate `@since` automatically.

Operational checklist and maintenance
-----------------------------------
 - Schema migrations: when deploying schema changes (for example adding `redirect_uri` to `oauth_start_tokens`) run the provided SQL or ALTER in staging before production.
 - Monitor logs for these event names when debugging flows: `oauth_start_token_created`, `oauth_start_token_consumed`, `oauth_callback_success`, `oauth_callback_error`.
 - Use `php -l` for quick syntax checks and the helper scripts in `tools/` for DocBlock checks.

Helper scripts
--------------
 - `tools/check_phpdoc.php` — scans `src/` for missing DocBlocks.
 - `tools/add_phpdoc.php` — conservative DocBlock generator (creates `.bak` backups). Run and review diffs before committing.

Suggested CI/quality integrations
--------------------------------
 - Add `php-cs-fixer` for style; add `PHPStan` for static analysis.
 - Add a simple PHPUnit or integration test harness that simulates the start/consume/callback flow for one provider (Google) in CI.

Roadmap and project decisions (current)
--------------------------------------
 - PHP target: PHP 8+
 - Core priority: Google OAuth support first; then add Meta, LinkedIn, Apple.
 - Tokens: design is stateless — do not persist user access/refresh tokens by default; provide extension points if persistent storage is required.
 - Compatibility: `_legacy` contains reference material; do not assume full behavioral compatibility with legacy URLs.

Licensing
---------
This project is licensed under GPL-3.0-or-later.

Author
------
Andrea Gaspari — Immaginificio

---

If you want, I can:
 - Add Composer scripts for DocBlock checks (`composer run phpdoc:check` / `phpdoc:apply`).
 - Create a small CI job skeleton for `php-cs-fixer` and `PHPStan`.
 - Open a PR with this README change and include a short commit message.

-----------------------------------------------------------------
Practical additions (examples, migrations, CI, troubleshooting)
-----------------------------------------------------------------

Integration example — WordPress (server-side)
---------------------------------------------
This minimal PHP example shows the server-side steps a WordPress theme/plugin should perform to start the server-to-server flow and handle the Bridge callback.

1) Request a start token (server-side):

```php
// server-side: request start token
$resp = wp_remote_post('https://bridge.example.com/auth/google/get-start-token', [
	'body' => [
		'site' => site_url(),
		'oauth_bridge_api_key' => $bridge_api_key,
		'client_wpnonce' => wp_create_nonce('imm_oauth'),
		'redirect_uri' => admin_url('admin-post.php?action=imm_oauth_callback'),
	],
	'timeout' => 10,
]);
$data = json_decode(wp_remote_retrieve_body($resp), true);
if (!empty($data['ok']) && !empty($data['token'])) {
	wp_redirect("https://bridge.example.com/auth/google/start-with-token?token=" . urlencode($data['token']));
	exit;
}
// handle error
```

2) Callback handler (`imm_oauth_callback_handler`) — simplified logic:

- If request is GET and contains `oauth_error` → show admin notice (no transient required).
- If request is POST → validate and consume the transient/nonce (`_wpnonce`) and save received tokens.

Example (very small):

```php
function imm_oauth_callback_handler() {
	if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['oauth_error'])) {
		// show admin notice with sanitized $_GET['oauth_error'] and optional provider
		return;
	}

	if ($_SERVER['REQUEST_METHOD'] === 'POST') {
		// validate expected transient/nonce from $_POST
		// save tokens securely (e.g. as option or transient linked to site)
	}
}
add_action('admin_post_imm_oauth_callback','imm_oauth_callback_handler');
add_action('admin_post_nopriv_imm_oauth_callback','imm_oauth_callback_handler');
```

Request / Response concrete examples
-----------------------------------

1) `POST /auth/google/get-start-token` request body (form-encoded or JSON):

```json
{
	"site": "https://example.com",
	"oauth_bridge_api_key": "S3CR3T-KEY-HERE",
	"client_wpnonce": "wpnonce_abc123",
	"redirect_uri": "https://example.com/wp-admin/admin-post.php?action=imm_oauth_callback"
}
```

Success response:

```json
{
	"ok": true,
	"token": "starttoken_abcdef123456"
}
```

2) Bridge POST to client `redirect_uri` on success (auto-submit form) — fields posted:

```
provider: google
access_token: (not recommended to log)
refresh_token: (not recommended to log)
expires_in: 3599
state: <state>
client_wpnonce: <restored_nonce>
```

SQL migration snippet
---------------------
If your `oauth_start_tokens` table lacks `redirect_uri`, apply the following ALTER in staging before production:

```sql
ALTER TABLE oauth_start_tokens
	ADD COLUMN redirect_uri VARCHAR(255) DEFAULT NULL AFTER client_wpnonce;
```

#oauth_error codes and recommended client messages
The Bridge may append `?oauth_error=<code>` on redirects. Use the following table to map machine-readable codes to user-facing messages (examples).

| Code | Meaning | Suggested user message (example) |
|------|---------|----------------------------------|
| invalid_state | State or nonce mismatch between request and callback | "The login attempt couldn't be verified. Please retry." |
| provider_error | Provider returned an error (user cancelled or provider-side failure) | "Login was cancelled or failed on the provider side. Please try again." |
| token_exchange_failed | Bridge failed to exchange `code` for tokens | "We couldn't complete the login. Please try again later." |
| missing_params | Required parameters missing in callback | "Invalid response from the provider. Contact support if the problem persists." |
| server_error | Internal error on the Bridge | "An internal error occurred. Please try again later." |
| rate_limited | Requests exceeded allowed rate | "Service is temporarily overloaded. Try again in a few minutes." |

Clients should display sanitized messages from the table above and log the raw `oauth_error` and correlated `api_key_last6` for debugging (do not log full tokens).

Redirect URI validation (algorithm)
----------------------------------
To accept a `redirect_uri` value from a client backend, use this rule-set:

1. If `redirect_uri` is a relative path (starts with `/`), accept it and prepend the site's origin.
2. If `redirect_uri` is an absolute URL, parse its host and compare to the registered `site` host in `site_keys` (exact match required). Reject if hosts differ.
3. If `redirect_uri` is missing, fall back to the client's default admin callback (e.g. `admin-post.php?action=imm_oauth_callback`).

Logging format and redaction example
-----------------------------------
Save only metadata and redacted values. Example log record (JSON):

```json
{
	"time":"2025-12-08T12:00:00Z",
	"site":"https://example.com",
	"action":"oauth_start",
	"api_key_last6":"a1b2c3",
	"token_last6":null,
	"payload":{
		"provider":"google",
		"has_client_wpnonce":true
	}
}
```

Redaction rules:
- `api_key_last6`: store only last 6 characters of API keys.
- `token_last6`: if present, store only last 6 characters of tokens (never store full tokens).
- Remove `access_token` and `refresh_token` from `payload` before saving logs.

Logging retention recommendations:
- Keep detailed request logs (with redacted fields) for a short troubleshooting window (e.g. 90 days).
- Keep high-level audit events (oauth_callback_success, oauth_callback_error) for longer if required by policy (6–12 months), but rotate and archive older logs.
- Make retention configurable and document the retention policy in deployment runbooks.

Composer scripts (recommended additions to `composer.json`)
-------------------------------------------------------
Add convenience scripts to expose project helpers:

```json
"scripts": {
	"phpdoc:check": "php tools/check_phpdoc.php",
	"phpdoc:apply": "php tools/add_phpdoc.php",
	"fix:php": "php-cs-fixer fix --config=.php-cs-fixer.php"
}
```

Minimal GitHub Actions CI (skeleton)
-----------------------------------
Create `.github/workflows/ci.yml` with:

```yaml
name: CI
on: [push, pull_request]
jobs:
	quality:
		runs-on: ubuntu-latest
		steps:
			- uses: actions/checkout@v4
			- name: Setup PHP
				uses: shivammathur/setup-php@v2
				with:
					php-version: '8.1'
			- name: Install deps
				run: composer install --no-interaction
			- name: PHPStan
				run: vendor/bin/phpstan analyse -l max src
			- name: PHP-CS-Fixer
				run: vendor/bin/php-cs-fixer fix --dry-run --diff
```

Useful curl commands for troubleshooting
----------------------------------------
Simulate the `get-start-token` call (replace values):

```bash
curl -X POST 'https://bridge.example.com/auth/google/get-start-token' \
	-d 'site=https://example.com' \
	-d 'oauth_bridge_api_key=YOUR_KEY' \
	-d 'redirect_uri=https://example.com/wp-admin/admin-post.php?action=imm_oauth_callback'
```

Simulate the refresh call:

```bash
curl -X POST 'https://bridge.example.com/auth/google/refresh' \
	-H 'Content-Type: application/json' \
	-d '{"site":"https://example.com","oauth_bridge_api_key":"YOUR_KEY","refresh_token":"REFRESH_TOKEN"}'
```

Example refresh response (success):

```json
{
	"ok": true,
	"access_token": "ya29.a0Af...",
	"expires_in": 3599,
	"scope": "https://www.googleapis.com/auth/...",
	"token_type": "Bearer"
}
```

LLM prompt templates (safe change requests)
------------------------------------------
When asking an LLM to modify code, include these constraints to avoid introducing security regressions:

Template 1 — Add Composer scripts:

```
Modify `composer.json` to add three scripts: `phpdoc:check`, `phpdoc:apply`, and `fix:php`. Do not change other keys. Ensure JSON remains valid.
```

Template 2 — Add DB migration:

```
Add a SQL migration file `migrations/2025_12_08_add_redirect_uri.sql` containing only the ALTER statement to add `redirect_uri` to `oauth_start_tokens`. Do not modify other files.
```

Troubleshooting checklist (quick)
--------------------------------
- Check `oauth_start_tokens` table for token rows and `used` status.
- Inspect Bridge logs for `oauth_callback_error` and the `api_key_last6` to correlate requests.
- Verify `redirect_uri` host equality with `site` stored in `site_keys`.
- Use the curl commands above to reproduce server-to-server calls.

Security operational runbook
----------------------------
This runbook lists immediate and medium-term actions for key rotation, revocation and incident response.

Key rotation (routine)
- Generate new `oauth_bridge_api_key` for the affected `site` in admin UI or DB (securely store the new key).
- Update the client site's server configuration to use the new key.
- Verify successful calls from the client using the new key.
- Revoke the old key after verification (set as inactive in `site_keys`).

Key revocation (compromise)
- Immediately mark the compromised key as revoked/inactive in `site_keys`.
- Notify the site owner and document the incident with `api_key_last6` in internal logs.
- Issue a replacement key and require rotation on the client.
- Search logs for suspicious activity using `api_key_last6` and `token_last6`.

Emergency steps after leak
- If a key leak is confirmed, revoke the key and rotate it as above.
- Invalidate or rotate any downstream secrets that could have been derived (if applicable).
- Increase log retention temporarily and capture full redacted request metadata for forensic analysis.
- Run a targeted audit for calls coming from the compromised `api_key_last6` and block offending IPs if necessary.

Access control & least privilege
- Limit admin access to key creation and revocation.
- Use secure channels (not email) to deliver new keys to site maintainers.

Post-incident
- Document the incident timeline and root cause.
- Perform a lessons-learned review and update runbooks.

