# Project Structure

## Root Files
Each PHP file at the root is a self-contained page — it handles both server-side logic (top) and HTML output (bottom).

| File | Role |
|---|---|
| `landing-page.php` | Public entry point. Hero page + auth modal (login/register for all roles) |
| `faculty-dashboard.php` | Faculty dashboard — borrow equipment, profile, request history (uses `tbl_users` / `$_SESSION['faculty_id']`) |
| `admin-dashboard.php` | Admin dashboard — inventory, request management, user management |

## Folders

```
/
├── includes/               # PHP logic helpers and AJAX endpoints
│   ├── admin-dashboard-functions.php   # All admin business logic (loaded by admin-dashboard.php)
│   ├── update-profile.php              # AJAX endpoint for user profile updates
│   ├── logout.php                      # Session destroy + redirect
│   └── lending_db.sql                  # Full DB schema + seed data
│
├── ajax/                   # Lightweight AJAX response scripts (return HTML fragments)
│   └── live-search.php     # Live search for admin request tables
│
├── css/                    # Stylesheets — one per page
│   ├── landing-page.css
│   ├── admin-dashboard.css
│   ├── faculty-dashboard.css
│   ├── user-dashboard.css
│   └── images-design/      # Hero carousel background images (1–7-hero-page.jpg)
│
├── JS/                     # JavaScript — one file per page, all vanilla ES6+
│   ├── landing-page.js
│   ├── admin-dashboard.js
│   ├── faculty-dashboard.js
│   └── user-dashboard.js
│
└── uploads/                # User-uploaded files (item images, profile pictures)
    ├── default.png          # Fallback image for inventory items
    └── profile_pictures/    # User profile photos
```

## Architecture Patterns

**Page structure:** Every dashboard PHP file follows this pattern:
1. `session_start()` + auth guard (redirect if not logged in)
2. DB connection via `mysqli_connect()`
3. AJAX action handlers at the top (check `$_POST['action']` or `$_POST['ajax_action']`, respond with JSON, `exit`)
4. Auto-status transitions (expire waiting requests, mark overdue)
5. Form POST handlers
6. Data fetching for page render
7. HTML output with embedded PHP

**Business logic separation:** `admin-dashboard-functions.php` is `require`d by `admin-dashboard.php` and handles all admin actions before HTML is rendered. Other pages keep logic inline.

**AJAX pattern:** AJAX calls return either JSON (`Content-Type: application/json`) for action responses, or raw HTML fragments for live search results. The `ajax/` folder holds scripts that only return HTML table rows.

**JavaScript pattern:** Each JS file is a single IIFE (`(function(){ 'use strict'; ... })()`). UI state (theme, settings, read notifications) is persisted to `localStorage` with an `adm_` prefix for admin. Event handling uses a single delegated `document.addEventListener('click', ...)` that dispatches on `data-action` attributes.

**CSS pattern:** All theming uses CSS custom properties on `:root`. Dark and high-contrast themes override variables via `[data-theme="dark"]` and `[data-theme="high-contrast"]` attribute selectors. No utility classes — styles are component-scoped per page stylesheet.

## Database Tables

| Table | Purpose |
|---|---|
| `tbl_users` | Faculty accounts (PK: `student_id`, format: `YYYY-XXXXX-BN-X`) — these are the borrowers |
| `tbl_faculty` | Unused / legacy table |
| `tbl_accounts` | Legacy admin account (plain-text password, single row) |
| `tbl_inventory` | Equipment items; `is_archived=1` for soft-deleted items |
| `tbl_requests` | Borrow requests; status: `Waiting / Approved / Declined / Overdue / Returned` |
| `tbl_room_reservations` | Room reservation requests; status: `Waiting / Approved / Declined / Cancelled` |

## Naming Conventions
- PHP files: `kebab-case.php`
- CSS files: `kebab-case.css`
- JS files: `kebab-case.js`
- DB tables: `tbl_` prefix, `snake_case`
- DB columns: `snake_case`
- CSS variables: `--kebab-case`
- JS functions: `camelCase`
- Uploaded files: `{timestamp}_{original_name}.{ext}`
