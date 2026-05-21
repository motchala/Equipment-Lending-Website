# Tech Stack

## Runtime & Server
- **PHP 8.2** (via XAMPP) — server-side logic, session management, form handling
- **MariaDB 10.4** (via XAMPP/phpMyAdmin) — database, accessed as `lending_db`
- **Apache** (via XAMPP) — local development web server
- No build system, bundler, or package manager — all assets are plain files served directly

## Frontend
- **Vanilla JavaScript** (ES6+, no frameworks) — wrapped in IIFEs with `'use strict'`
- **CSS Custom Properties** — theming via `:root` variables, no preprocessor
- **Font Awesome 6** (CDN) — icons throughout the UI
- **Google Fonts** (CDN) — Cormorant Garamond + Outfit
- No jQuery, no React, no Vue, no TypeScript

## Database
- Database name: `lending_db`
- Connection: `mysqli_connect("localhost", "root", "", "lending_db")`
- Uses both procedural `mysqli_*` functions and OOP `$conn->prepare()` — both styles exist in the codebase
- Passwords hashed with `PASSWORD_BCRYPT` via `password_hash()` / `password_verify()` (except legacy `tbl_accounts` which stores plain text)

## Key Libraries / External Dependencies
| Dependency | Source | Usage |
|---|---|---|
| Font Awesome 6.0.0 | cdnjs CDN | Icons |
| Google Fonts | fonts.googleapis.com | Cormorant Garamond, Outfit |

## Common Commands

This project runs entirely through XAMPP — there are no CLI build or test commands.

```
# Start the project
1. Open XAMPP Control Panel
2. Start Apache and MySQL services
3. Visit http://localhost/Equipment-Lending-Website/landing-page.php

# Database setup
Import includes/lending_db.sql via phpMyAdmin into a database named `lending_db`

# Default admin credentials
Email:    main@admin.edu
Password: admin123
```

## Timezone
All PHP files set `date_default_timezone_set('Asia/Manila')` at the top of entry points.
