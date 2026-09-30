# PUPSync

Institutional equipment-lending and room-reservation platform for PUP faculty, students, and admins. Three portals (Student, Faculty, Admin), one shared database, and a rule-based arbitration engine that auto-approves or declines borrow/reservation requests without human intervention.

**Live entry point:** `landing-page.php` (unified login for Faculty + Admin — see [Login flow](#login-flow))

---

## Tech stack

| Layer | Choice |
|---|---|
| Language | PHP 8+ (procedural + OOP, `declare(strict_types=1)` in newer modules) |
| Database | MySQL / MariaDB via **MySQLi** (prepared statements throughout, no ORM/PDO) |
| Frontend | Server-rendered PHP + vanilla JS (no framework/bundler), Bootstrap 5.3, Material Symbols, Font Awesome |
| Mail | PHPMailer (vendored under `vendor/PHPMailer`, no Composer) via Gmail SMTP |
| QR codes | `qrcode.js` (generate return tokens) + `jsQR` (camera-based scanning) via CDN |
| Auth/session | Native PHP sessions, hardened cookies (HttpOnly, SameSite=Strict, Secure on HTTPS) |
| CI/CD | GitHub Actions → FTP deploy to InfinityFree shared hosting on push to `main` |

There is **no build step and no Composer/npm dependency manager** — everything is `require_once`'d directly. This is intentional (constraint of the free shared-hosting target), not an oversight.

## Architecture

Monolithic, module-per-feature PHP app. No MVC framework — each role has one large "portal" file that renders the whole SPA-like dashboard, backed by small stateless JSON API endpoints for AJAX/polling.

```
├── landing-page.php          # Unified login (Faculty + Admin) — public entry point
├── student-dashboard.php     # Student portal (code-based access, no login)
├── faculty-dashboard.php     # Faculty portal (borrowing + room reservation UI)
├── admin-dashboard.php       # Admin/Super Admin control center
│
├── equipment-booking/        # Equipment-lending module
│   ├── api/                  #   AJAX endpoints (submit, verify, poll, admin actions)
│   ├── core/                 #   Business logic (see below)
│   └── assets/                #   Module-scoped CSS/JS
│
├── room-reservation/         # Room-booking module (mirrors equipment-booking's shape)
│   ├── api/
│   ├── core/
│   └── assets/
│
├── assets/                   # Shared static files: landing-page + role-splash css/js, fonts, images
│
├── config/                   # Cross-cutting bootstrap, required by every entry point
│   ├── db.php                #   getDB() — cached MySQLi connection (gitignored, see Setup)
│   ├── env.php                #   Minimal .env parser → $_ENV
│   ├── session.php             #   Hardened session_start() wrapper
│   ├── csrf.php                #   csrf_token() / csrf_field() / csrf_verify()
│   ├── security-headers.php     #   CSP/X-Frame-Options fallback for AJAX endpoints
│   └── role-splash.php          #   role_splash_head() / render_role_splash() — post-login overlay markup
│
├── database/lending_db.sql   # Full schema + seed data (idempotent migrations baked in as
│                              #   "ALTER TABLE IF NOT EXISTS column" guards — see below)
├── vendor/PHPMailer/         # Vendored library (no Composer)
├── uploads/                  # User-submitted files: item photos, profile pictures, request letters
└── .github/workflows/deploy.yml
```

**Why "core" is split from "api":** `core/*.php` holds pure(r) business logic and is `require`'d by multiple callers; `api/*.php` files are thin HTTP endpoints — they handle the request, call into `core/`, and echo JSON. `admin-dashboard.php` and `faculty-dashboard.php` also `require_once` `core/` files directly to render initial page state without an AJAX round-trip.

**Self-healing schema:** several files (e.g. the login handler, `notif-functions.php`) run a `SHOW COLUMNS` / `information_schema` check on first use and `ALTER TABLE` if a column/table is missing. This lets an older deployed database catch up to schema changes without a manual migration step — keep this pattern in mind when adding columns.

## Roles & interfaces

| Role | How they get in | Portal | Notes |
|---|---|---|---|
| **Student** | No account. A Faculty member issues a one-time alphanumeric code (`tbl_faculty_codes`) tied to a request | `student-dashboard.php` | Code-verify → form → result, all in one screen |
| **Regular Faculty** | Registered account (`tbl_users`, `role = 'Regular Faculty'`) | `faculty-dashboard.php` | Borrows equipment/rooms directly, or issues codes to students |
| **Organization Adviser** | `tbl_users`, `role = 'Organization Adviser'`, `is_org_adviser = 1`, linked to `tbl_organizations` | `faculty-dashboard.php` | Requests on behalf of a student org; needs a signed document unless a Director's signature is detected |
| **Admin** | `tbl_accounts`, `role = 'Admin'` | `admin-dashboard.php` | Manages inventory, requests, rooms, arbitration config |
| **Super Admin** | `tbl_accounts`, `role = 'Super Admin'` | `admin-dashboard.php` | Everything Admin can do, plus creating/managing other Admin accounts |

## Login flow

`landing-page.php` is a **single unified login form** — the user isn't asked whether they're Faculty or Admin. On submit it:
1. Runs two layers of rate-limiting pre-checks (IP-wide, then email+IP pair) — see `rate-limiter.php`.
2. Looks the email up in `tbl_accounts` (Admin) first, then `tbl_users` (Faculty) if no match.
3. Redirects to whichever dashboard matches.

Students never authenticate here at all — they enter through a faculty-issued code on `student-dashboard.php`.

## Main modules / features

### 1. Equipment booking (`equipment-booking/`)
- Faculty submit borrow requests, or generate a one-time code for a student to submit on their behalf.
- Every new request is evaluated **synchronously and automatically** by the Arbitration Engine (below) — there is no manual "pending → approve" admin step for the common case.
- Approved borrows get a QR-coded return token; Admin scans it via `return_confirm.php` (or a student/admin can hit the confirm link) to mark an item Returned and restock inventory.
- Live polling endpoints (`poll-*.php`) keep the dashboards in sync without a page reload.

### 2. Room reservation (`room-reservation/`)
- Mirrors the equipment flow but for physical spaces: **Campus → Building → Room** hierarchy (`tbl_campuses` / `tbl_buildings` / `tbl_rooms`).
- Adds a **waitlist** (`tbl_room_waitlist`) for fully-booked slots and an **issue-reporting** flow (`tbl_room_issues`) for maintenance problems, both visible to Admins.
- Runs through the same arbitration engine class, with room-specific rules (e.g. an Adviser with no document is Declined immediately for rooms, vs. held as Waiting for equipment, since rooms have no stock queue to hold a place in).

### 3. Arbitration Engine (`equipment-booking/core/arbitration-engine.php`)
The core differentiator of this project — a deterministic, rule-based scoring engine (not literal AI, but framed as "AI-driven" auto-decisioning in-code) that replaces manual admin approval. Entry point: `ArbitrationEngine::process($conn, $request_id)`, called immediately after a request is inserted.

Decision flow: load config (`tbl_arbitration_config`) → pre-flight blocks (overdue borrower, duplicate request, missing required document, archived item, out-of-stock) → acquire a row lock on inventory (`SELECT ... FOR UPDATE`) → score and rank competing Waiting requests by:
1. FIFO (submission order)
2. Signatory level detected in the uploaded document (Director > none)
3. Role priority (configurable: Director > Adviser > Faculty > Student)
4. Return history (fewer past late returns wins ties)
5. ID order (final deterministic tiebreaker)

Every decision — approve, decline, or hold — is written back to `tbl_requests.status` and logged to `tbl_arbitration_log`. Rule weights and toggles are editable by Admins at runtime via `save-arbitration-config.php`, no deploy required.

### 4. Admin control center (`admin-dashboard.php` + `*-functions.php`)
Inventory CRUD (with archiving, not hard deletes), request oversight and manual override (`admin-override.php`, for the rare case arbitration needs correcting), room/building/campus management, arbitration rule configuration, Admin/Super-Admin account management, and a live notification feed.

### 5. Notifications (`notif-functions.php`)
Deliberately **not stored as generated events** — the feed is computed fresh on every load from the current state of `tbl_requests`, `tbl_room_issues`, and `tbl_inventory` (e.g. "3 items overdue" is a live query, not a row someone inserted). Only read/deleted state per notification persists, in `tbl_notif_state`, keyed by a synthetic id like `overdue-12`.

### 6. Role splash (post-login transition)
A short full-screen overlay (PUPSYNC wordmark → role name: Admin / Super Admin / Faculty / Student) shown **only right after a successful sign-in** — never on a refresh or ordinary navigation. `landing-page.php` arms a one-shot session flag (`role_splash_arm()`) just before redirecting; the dashboard consumes it on that first load. Logging out destroys the session, so the next login shows it again. Students don't log in, so "Continue as Student" routes through `landing-page.php?go=student`, which arms the flag for them. Purely cosmetic — no auth or DB logic. Three files: `config/role-splash.php` (markup helper + flag), `assets/css/role-splash.css` (look + entire timeline, tunable via the `--rs-t-*` variables), `assets/js/role-splash.js` (cleanup/skip only). Each dashboard integrates it with three lines: a `require_once`, `role_splash_head()` in `<head>`, and `render_role_splash('<Role>')` right after `<body>`.

### 7. Responsive admin (mobile / tablet)
The admin portal adapts to phones and tablets without changing the desktop layout (verified pixel-identical at 1200/1440/1920px). The base `admin-dashboard.css` already carries the off-canvas sidebar drawer and its 1024/768/480/400px breakpoints; `equipment-booking/assets/css/admin-dashboard-responsive.css` (loaded after it) adds what was missing, and `equipment-booking/assets/js/admin-dashboard-responsive.js` (deferred) prepares the markup it needs. **Tablet (769–1024px):** icon-rail sidebar, tighter table padding and scroll-shadow hints. **Phone (≤768px):** every data table (`.ps-table`, `.pr-table`, `.admin-table`) becomes stacked cards — the JS copies each column header onto its cells as `data-label` and tags the headline, thumbnail and actions cells, and re-runs whenever live-render/AJAX swaps rows in, so new tables need no extra work; the Room Schedule's Mon–Fri grid becomes a one-day agenda with day tabs; touch targets are ≥44px; text inputs are 16px so iOS doesn't auto-zoom. No existing class, id or script was renamed or modified.

### 8. Security hardening
This codebase has clearly been through an OWASP ZAP pass — worth knowing before you "simplify" something:
- Per-request CSP with a nonce for inline scripts (`landing-page.php`, dashboards) or a static fallback CSP for API/utility files (`config/security-headers.php`).
- Custom CSRF tokens (`config/csrf.php`), checked on every state-changing POST, with a header fallback (`X-CSRF-TOKEN`) for JSON/AJAX calls.
- Hardened session cookies enforced at both the `ini_set` and `session_set_cookie_params` level (some shared hosts ignore one or the other).
- Escalating login lockouts: 3 fails → lockout, doubling in severity (5 min → 15 min → 60 min) per email+IP pair, with lazy passive decay — see the doc-comment in `rate-limiter.php` for the full state machine.

## Data model (key tables)

Full schema with all columns lives in `database/lending_db.sql`. Summary:

<details>
<summary>Expand table reference</summary>

| Table | Purpose |
|---|---|
| `tbl_accounts` | Admin/Super Admin logins |
| `tbl_users` | Faculty logins (Regular Faculty / Organization Adviser) |
| `tbl_faculty_codes` | One-time codes a Faculty issues so a Student can submit a request |
| `tbl_organizations` | Student orgs an Adviser can request on behalf of |
| `tbl_inventory` | Borrowable equipment catalog (quantity, condition, photo, archive flag) |
| `tbl_requests` | Equipment borrow requests + status + return QR token |
| `tbl_arbitration_config` | Runtime-editable weights/toggles for the arbitration engine |
| `tbl_arbitration_log` | Audit trail of every arbitration decision |
| `tbl_campuses` / `tbl_buildings` / `tbl_rooms` | Room location hierarchy |
| `tbl_room_reservations` | Room booking requests + status |
| `tbl_room_waitlist` | Queue for fully-booked room slots |
| `tbl_room_issues` | Maintenance/issue reports on a room |
| `tbl_login_attempts` / `tbl_ip_login_attempts` | Rate-limiter state |
| `tbl_notif_state` | Read/deleted flags for the computed-live notification feed |

</details>

## Getting started (local setup)

**Requirements:** PHP 8.0+ (with the `mysqli` and `mbstring` extensions), MySQL/MariaDB, Apache (or any server that honors `.htaccess`/`mod_headers`), a mail account for SMTP (Gmail used in dev).

1. **Clone** the repo and serve it from a local stack (XAMPP/Laragon/MAMP) — there's no `artisan serve`/built-in dev server, this is plain Apache+PHP. `.htaccess` sets `DirectoryIndex landing-page.php` as the site root.
2. **Database:** import `database/lending_db.sql` into a fresh schema (matches the default name `lending_db`).
3. **Environment file:** copy `.env.example` → `.env` and fill in:
   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=lending_db

   MAIL_USERNAME=
   MAIL_PASSWORD=       # Gmail App Password, not your login password
   MAIL_FROM_NAME=PUPSync Notifications
   ```
   (`.env.example` currently only ships the mailer keys — the DB_* keys above are read by `config/db.php`/`env.php` and must be added by hand until the example file is updated.)
4. **`config/db.php` is gitignored** (intentionally, per its own doc-comment) and won't exist on a fresh clone. Recreate it at `config/db.php` — it contains no real code beyond reading `.env`, so this is safe to restore verbatim:
   ```php
   <?php
   require_once __DIR__ . '/env.php';
   load_env();

   function getDB(): mysqli
   {
       static $conn = null;
       if ($conn !== null) return $conn;

       $host = $_ENV['DB_HOST'] ?? 'localhost';
       $user = $_ENV['DB_USER'] ?? 'root';
       $pass = $_ENV['DB_PASS'] ?? '';
       $name = $_ENV['DB_NAME'] ?? 'lending_db';

       $conn = @new mysqli($host, $user, $pass, $name);
       if ($conn->connect_error) {
           error_log('[PUPSync] Database connection failed: ' . $conn->connect_error);
           http_response_code(500);
           exit('Service temporarily unavailable.');
       }
       $conn->set_charset('utf8mb4');
       return $conn;
   }
   ```
5. Visit `landing-page.php` in the browser. Seed admin login is in `tbl_accounts` (`main@admin.edu`) — reset its password hash directly in the DB if you don't have the original plaintext.

## Deployment

`.github/workflows/deploy.yml` deploys straight to **InfinityFree** shared hosting via FTP on every push to `main` (`FTP_HOST` / `FTP_USER` / `FTP_PASS` repo secrets). `.env`, `config/db.php`, and `.git*` are excluded from the sync. There is no staging environment — treat `main` as production.

## Conventions to follow when contributing

- **No Composer/npm.** New PHP libraries get vendored under `vendor/`; new JS libraries get pulled from a CDN inline, matching the existing pattern.
- **Prepared statements only** for anything touching user input (a few legacy `mysqli_query` string-interpolation spots exist — don't copy them, use `bind_param`).
- **Every state-changing POST needs `csrf_verify()`** and every entry point needs its own CSP header (or `require`s `config/security-headers.php`).
- Schema changes should be additive and guarded (`SHOW COLUMNS` / `information_schema` check + `ALTER TABLE`) so already-deployed databases self-heal, matching the pattern in `notif-functions.php` and the login handler — don't assume `lending_db.sql` was just freshly imported.
- Keep `core/` (logic) and `api/` (thin HTTP wrappers) separated when adding a feature to either module.
