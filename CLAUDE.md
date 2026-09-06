# Project rules

Multi-tenant SaaS e-commerce platform (Shopify-style). Merchants subscribe to a
package, get a store provisioned automatically, connect their own domain, and
manage everything from a store admin dashboard.

Markets: Bangladesh first, then Malaysia, Indonesia, UAE, Saudi Arabia.

## Who you are working with

The project owner directs the work but **does not read code**. Because of that:

- Explain what you changed in plain, simple English. No code in explanations
  unless asked. Short sentences.
- Before any change that is hard to undo (dropping a column, deleting files,
  changing how money or orders are stored, touching tenancy), say what you are
  about to do and wait for a "yes".
- After each change, tell the owner exactly what to open in the browser to
  check it, e.g. "open https://devecom.gotipay.com/admin/products".
- If an instruction is ambiguous, ask one short question. Never guess on
  anything involving money, orders, stock, or tenant data.
- Never say something is done if it is not tested. Run it first.

## Server

- Dev server only. Never connect this to production data or live payment keys.
- Ubuntu 26.04, PHP 8.5, PostgreSQL, Redis, Caddy, Supervisor.
- Project lives at /var/www/platform, running as the `deploy` user.
- Site: https://devecom.gotipay.com  (store subdomains: *.devecom.gotipay.com)

## Stack

- Laravel 13, PHP 8.5
- Blade + Livewire for BOTH storefront and admin. No Next.js, no React.
- PostgreSQL only. Never MySQL.
- Redis for cache and sessions; queues via Redis, with an outbox table for
  anything financial.
- Local disk for uploads through the `Storage` facade. Never `file_put_contents`
  with a hardcoded path — S3 comes later.

## Non-negotiable architecture rules

Expensive or impossible to fix later. Never break them, even if an instruction
seems to ask for it — explain the problem instead.

**1. Every tenant-owned table has `tenant_id`.**
Use the `BelongsToTenant` trait. Composite indexes lead with `tenant_id`.
Unique constraints are scoped: `UNIQUE (tenant_id, slug)`, never `UNIQUE (slug)`.

**2. Never write a query that can cross tenants.**
No `DB::table()` on tenant data. No `withoutGlobalScope('tenant')` outside the
super-admin path, and there it must be logged to `audit_logs`. Every new
tenant-owned model gets added to the isolation test.

**3. Money is integer minor units plus a currency code.**
Store the currency exponent — Indonesian Rupiah is effectively zero-decimal, and
treating it like dollars makes prices 100x wrong. Never float. Format only in
the view layer.

**4. Stock changes atomically in the database.**
`UPDATE inventory_levels SET available = available - ? WHERE id = ? AND available >= ?`
Zero affected rows means out of stock. Never read-then-write. Redis is never the
source of truth for stock.

**5. Financial records are immutable.**
Refunds, cancellations and returns are NEW rows referencing the order. Never
change an order's totals after it is placed.

**6. Payment webhooks must be idempotent.**
Store the provider's `event_id` with a unique constraint and exit early if it
already exists. Duplicate webhooks are normal.

**7. Side effects go through an outbox.**
When an order is created, write an `outbox_events` row in the same transaction.
A worker reads that table. Never rely on a queued job alone for anything
financial — Redis can lose jobs.

**8. Payment gateway credentials are write-only.**
Encrypted at rest, in `$hidden`, never returned by any API or view (last 4 only),
never logged. Rotation is a write, never a read-modify-write.

**9. No third-party PHP plugins, ever.**
"Plugins" are first-party modules in this repo, switched on per tenant by an
entitlement row. Never load external PHP packages at runtime.

**10. Merchants never write code.**
Themes are Blade components. Pages are JSON lists of sections with typed
settings. Nothing a merchant types is executed or `eval`'d.

**11. Themes must be RTL-capable from the first theme.**
Arabic is a target market. Retrofitting right-to-left later means touching every
template.

**12. Tax is per store, per country, with inclusive/exclusive display.**
Gulf shops show VAT-inclusive prices; other markets often show exclusive.
Timezone is per store too — never use the server's.

**13. Migrations must be safe on a live table.**
Expand then contract. Never rename or drop a column in the same deploy that
stops using it.

## Custom domain flow (already decided)

1. Merchant enters their domain in the store admin.
2. We show one A record pointing at our server IP (plus `www`).
3. A scheduled job checks DNS. When it resolves to our IP, mark it `verified` —
   that resolution IS the proof of ownership. No separate TXT record.
4. Caddy calls `GET /internal/domain-check?domain=...` before issuing a
   certificate. Return 200 ONLY for hostnames that are either a known store
   subdomain or a verified custom domain. Everything else returns 404.
5. Merchants on Cloudflare must set the record to DNS only (grey cloud).

## Git workflow

Two branches:

- **`dev`** — where all work happens. You commit here. Always.
- **`main`** — approved for production. Never commit to it.

Merging dev into main is the owner's decision, made by running `golive`. If asked
to put something live, remind the owner to run it themselves.

Commit after every working change:

- Message in plain English saying what changed and why. Not "fix bug" —
  "stop the cart showing prices without tax".
- One commit per logical change.
- Push to `origin dev` after committing.
- Never leave the repo dirty at the end of a session. Unfinished work commits as
  "WIP: <what is not done>".
- Never `git push --force`, never rewrite history.
- Never commit `.env`, `auth.json`, keys or passwords. If a secret is needed, add
  the key name to `.env.example` empty and tell the owner to fill it in.

If the owner says "undo that": show `git log --oneline -10` in plain language,
ask which point to return to, then use `git revert` — never `git reset`.

Git holds code only. The database and uploads are backed up separately. Never
tell the owner their data is safe because it is "in git".

## Other rules

- Before any migration, run `/usr/local/bin/backup-db.sh`.
- Never run `migrate:fresh`, `db:wipe`, `rm -rf`, drop or truncate without asking.
- One test must always pass: `php artisan test --filter=TenantIsolation`.
  Run it before every commit. A new model without `tenant_id` should fail it.

## Useful commands

```bash
cd /var/www/platform
php artisan migrate
php artisan test
sudo supervisorctl restart all
sudo systemctl reload caddy
sudo -u postgres psql platform
tail -f storage/logs/laravel.log
journalctl -u caddy -f
/usr/local/bin/backup-db.sh
```

## Scope for version 1

Build: products and variants, categories, brands, inventory, orders, checkout,
cart, one payment gateway, cash on delivery, one courier, one storefront theme,
store admin, subscription packages and limits, custom domains, super admin,
email and SMS notifications.

Do NOT build yet — remind the owner these were deferred: third-party plugin
marketplace, drag-and-drop page builder, extra themes, WhatsApp and web push,
multi-warehouse, multi-language storefronts, blog/CMS, public merchant API,
nameserver-based domains, ERP/dropshipping/marketplace integrations.
