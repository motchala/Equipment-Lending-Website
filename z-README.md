# PUPSync

PUPSync is a website for **PUP Biñan Campus** where faculty borrow equipment and reserve rooms, students do the same with a code from their faculty, and admins keep track of everything in one place.

This guide has two parts: **[Set it up](#set-it-up-on-a-computer)** (no coding needed) and **[Notes for developers](#notes-for-developers)** at the bottom.

---

## Who uses it

| Who | How they get in | What they do |
|---|---|---|
| **Student** | No account. They enter a code from their faculty on the Student Portal. | Borrow equipment or reserve a facility |
| **Faculty** | Account created by a Super Admin, with a generated password they must replace on first sign-in | Borrow equipment, reserve rooms, track everything in My Activity, give codes to students |
| **Admin** | Account created by a Super Admin | Review requests, scan QR codes, manage equipment and facilities |
| **Super Admin** | Seed account or created by another Super Admin | Everything an Admin does, plus faculty and admin accounts, imports and rules |

Everyone signs in on the same page (`landing-page.php`). The system works out whether you are faculty or admin from your email. Students use the **Continue as Student** option instead.

## What it does

**Equipment**
- Faculty pick items, a room (or "Outside") and a time: today for 1, 2, 3 or 5 hours, or a later date up to 3 weeks ahead.
- A **same-day** request is decided by the system straight away, based on availability and the borrowing rules. A **later-date** request waits for an admin to **approve or decline** it, and can carry a request letter (recommended, never required). If nobody decides, it is **declined automatically at 12:01 AM on the borrow date**, so the faculty knows with the whole day left. Nothing is ever auto-approved.
- Once approved, the faculty gets a **Claim QR** in *My Activity*. The admin scans it at the Admin Office and the item becomes *On Loan*.
- On return, the faculty shows a **Return QR** and the admin scans it. The stock goes back automatically. Admins can also record a manual return without a QR.
- A collected item that is not back by its return time becomes *Overdue* (only items that are actually out can be overdue). Optionally, the *overdue block* rule (Super Admin > rules, off by default) stops that faculty from borrowing more until it is returned. They can *Extend* an item that is due or overdue up to 2 times if nobody else needs it.
- An approved booking that is never collected ends as *Cancelled* (a no-show) when its borrow period is over, and its units go back on the shelf.
- Students with a faculty code or item code follow the **same rules as faculty**: a same-day request is decided right away (approved with their own Claim QR, or told why not), and a later-date request waits for an admin and is declined automatically at 12:01 AM on that date if nobody decides.

**Rooms (Facilities)**
- Rooms are organised by campus, building and floor. Booking hours are 7:00 AM to 8:00 PM, Monday to Saturday.
- A **same-day** booking is confirmed straight away if the slot is free. A **later-date** booking waits for an admin, and is declined automatically at 12:01 AM on that date if nobody decides. A request letter can be attached to a later-date booking (recommended, never required).
- Each faculty can hold up to 2 active reservations.
- Faculty can report room problems, join a waitlist for taken slots, and ask the built-in **room assistant** (an AI chat limited to 20 messages per hour).

**For admins**
- A **Dashboard** with what needs attention, an **Activity** timeline of every decision, notifications, and exports.
- **Equipment** (catalog and loans) and **Facilities** (rooms, reservations, issues, archive).
- Super Admin only: **Faculty** accounts (the password is generated automatically, with a copy button), **Admins** (up to 5), and **Imports** (sidebar, under Data) for the faculty registry and the **Rooms Assigned** class schedule (an XLSX with each class's faculty, room and meeting times; it is separate from the registry and blocks those rooms for reservations). The **Equipment Inventory** import card is there too, but it is not built yet.
- Both portals have a built-in **Help & Support** page inside Settings.

---

## Set it up on a computer

These steps use **XAMPP on Windows**. Mac and Linux work the same way (use XAMPP, MAMP or any Apache + PHP + MySQL/MariaDB setup).

### What you need
- **XAMPP** with PHP 8.0 or newer (download it from apachefriends.org). It includes Apache, MySQL and phpMyAdmin.
- An internet connection when using the site (fonts, icons and the QR scripts load from the internet).

### Steps

1. **Start the servers.** Open the XAMPP Control Panel and click **Start** next to **Apache** and **MySQL**.

2. **Put the project in `htdocs`.** Copy the whole `Equipment-Lending-Website` folder into `C:\xampp\htdocs\`.

3. **Load the database.**
   - Open `http://localhost/phpmyadmin` in your browser.
   - Click the **Import** tab, choose `database/lending_db.sql` from the project, and click **Import** at the bottom.
   - You do not need to create the database first. The file creates one called `lending_db`. If one already exists, it is replaced.

4. **Create your two settings files.** In the project folder, make a copy of each file and rename the copy:

   | Copy this | To this |
   |---|---|
   | `.env.example` | `.env` |
   | `config/db.example.php` | `config/db.php` |

   Tip for Windows: turn on **View → File name extensions** in File Explorer so the copy is not accidentally named `.env.txt`.

5. **Check the database settings in `.env`.** For a normal XAMPP install you can leave them as they are:

   ```
   DB_HOST=localhost
   DB_USER=root
   DB_PASS=
   DB_NAME=lending_db
   ```

6. **Open the site.** Go to `http://localhost/Equipment-Lending-Website/` and sign in.

### Signing in the first time

The database comes with two admin accounts: **`main@admin.edu`** (Super Admin) and **`chico@admin.edu`** (Admin). The passwords are not written anywhere in the project, so set a new one for the account you want to use:

1. Open the XAMPP **Shell** and run (change the password text first):
   ```
   php -r "echo password_hash('MyNewPassword1!', PASSWORD_DEFAULT);"
   ```
2. Copy the long text it prints.
3. In phpMyAdmin, open `lending_db` → `tbl_accounts`, click **Edit** on the account, paste the text into the `password` box, and click **Go**.
4. Sign in with that email and your new password.

### Things to set up after signing in
As a Super Admin, go to **Imports** (sidebar, under Data) and import the official **Faculty Registry**. Then use the **Faculty** tab to create faculty accounts (it looks up each person in the registry). Add your equipment under **Equipment → Catalog** and your rooms under **Facilities**.

---

## Using it from other devices

To let other computers or phones on the **same Wi-Fi** open the site:

1. Find the host computer's IP address (in Windows, run `ipconfig` and look for *IPv4 Address*, for example `192.168.1.20`).
2. On the other device, open `http://192.168.1.20/Equipment-Lending-Website/`.
3. If it will not load, allow **Apache** through the Windows Firewall when Windows asks.

The QR codes the system creates already use the host computer's network address, so they work from other devices. Note that most browsers only allow camera access (used by **Scan return** on the admin Dashboard) on the host computer itself (`localhost`) or on an `https://` site, so scan on the host computer or use a secure address.

## Optional settings

Open `.env` to turn these on. The system still works without them.

**Email notices** (for example, cancellation notices and waitlist alerts) use a Gmail account:
```
MAIL_USERNAME=your.address@gmail.com
MAIL_PASSWORD=your-16-letter-app-password
MAIL_FROM_NAME=PUPSync Notifications
```
`MAIL_PASSWORD` must be a Google **App Password** (Google Account → Security → 2-Step Verification → App passwords), not your normal Gmail password.

**Room assistant (AI chat).** Add one or more Google Gemini keys, with an optional Groq key as a backup:
```
AI_API_KEY=
AI_API_KEY2=
AI_API_KEY3=
GROQ_API_KEY=
```
Without a key, the room assistant will not answer.

## If something goes wrong

| What you see | What to try |
|---|---|
| "Service temporarily unavailable" | The database is not reachable. Check that MySQL is started and that `.env` and `config/db.php` both exist with the right `DB_` values. |
| Blank page or a missing-file error | `config/db.php` or `.env` is missing. Redo step 4. |
| Page looks plain (no fonts or icons) | The computer is offline. Fonts, icons and the QR scripts come from the internet. |
| `404` or `403` error | Open the exact address `http://localhost/Equipment-Lending-Website/` and check the folder name matches. |
| Error 500 right after setup | The first lines of `.htaccess` block `.env`. If your server does not support them, delete that `<Files ".env">` block. |
| Photos or letters will not upload | The `uploads` folder must be writable. On Mac/Linux run `chmod -R 775 uploads`. |
| Emails are not arriving | Check the Gmail App Password in `.env`. The system keeps working if email fails. |
| Locked out after wrong passwords | Repeated wrong passwords lock sign-in for a few minutes. Wait and try again. |

---

## Notes for developers

### Tech stack
- **PHP 8+** (needs `mysqli`, `mbstring`, `fileinfo` and `zip`), MySQL/MariaDB through **MySQLi** with prepared statements.
- Server-rendered PHP with plain JavaScript and CSS. There is **no build step and no Composer/npm**. New PHP libraries are vendored under `vendor/` (PHPMailer is already there).
- QR codes: `qrcode.js` (create) and `jsQR` (scan) from a CDN.
- Sign-in uses native PHP sessions with hardened cookies, an optional 30-day "remember me", and escalating lockouts for repeated failures.

### Folder layout

```
├── landing-page.php          # Sign-in page
├── student-dashboard.php     # Student Portal (code-based, no account)
├── faculty-dashboard.php     # Faculty portal entry
├── admin-dashboard.php       # Admin / Super Admin entry
│
├── auth/                     # Login, logout, rate limiter, remember-me, role splash
├── dashboard/                # Dashboard data and screens, plus the portal CSS/JS
├── equipment-booking/        # Borrowing, loans, QR claim/return, extensions, cancellations
├── room-reservation/         # Rooms, reservations, waitlist, issues, room assistant
├── account-management/       # Faculty and admin accounts, registry uploads
├── notifications/            # Notification feeds
├── activity/                 # Activity feeds and My Activity
├── settings/                 # Profile, preferences, imports, Help & Support
├── shared/                   # Shared helpers, page shells, logout dialog
│
├── config/                   # db, env, session, csrf, security headers
├── database/lending_db.sql   # Full schema and starting data (never deploy this folder)
├── vendor/PHPMailer/         # Vendored library
├── uploads/                  # User-uploaded files
└── .github/workflows/deploy.yml
```

Each feature folder follows the same shape: `api/` (small endpoints that return JSON), `core/` (logic, include-only), `views/` (page pieces, include-only) and `assets/` (that module's CSS/JS). `core/` and `views/` are blocked from direct web access by a `.htaccess`.

### How decisions are made
- **Equipment requests**: a same-day request from the faculty form is run through `arbitration-engine.php` as soon as it is saved and is approved or declined on the spot. A later-date request is stored as *Waiting* and decided by an admin (`admin-override.php`, `approve-borrow-bundle.php`). Student borrows made with a code are stored as *Approved* straight away. Status flow: Waiting → Approved → On Loan → Returned, with Overdue, Declined and Cancelled as side paths.
- **Room reservations** use `equipment-booking/core/arbitration-engine.php`: same-day bookings are checked and confirmed on the spot, later dates are held as *Waiting* for an admin (`room-expiry.php` declines undecided ones).
- Booking limits (hours, advance days, extension counts) live in `equipment-booking/core/booking-schedule.php` and `borrow-extension.php`. Room limits live in `room-reservation/core/room-quota.php`.
- Notifications are computed from the current data on each load; only read/deleted state is saved.

### Database changes
The Organization Adviser role was removed. A database imported before that change should run `database/migration_remove_org_adviser.sql` once (back up first). A fresh import of `lending_db.sql` already has the new structure.

Several files check for missing columns/tables on first use and add them (`ensureSchema()` and similar), so an older database catches up by itself. Keep schema changes additive and guarded the same way, and don't assume the SQL file was freshly imported.

When you export a new `lending_db.sql` from phpMyAdmin, make sure these lines are at the very top so it imports cleanly on another computer:

```sql
DROP DATABASE IF EXISTS `lending_db`;
CREATE DATABASE `lending_db`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;
USE `lending_db`;
```

### First sign-in lock (new faculty accounts)
The Faculty tab generates a random temporary password in the browser (Web Crypto) and saves the account with `tbl_users.must_change_password = 1`. While that flag is set, `faculty_session_guard()` (`auth/core/admin-auth.php`, runs on every request) sends pages to `faculty-setup.php` and answers API calls with a 403 (`setup_required`). The setup screen has two steps (new password, then department) and saves them together through `account-management/api/first-login-setup.php`, which also clears the flag, so it is never asked again. Only `faculty-setup.php`, that endpoint and `auth/api/logout.php` stay open while locked (`FACULTY_FIRST_LOGIN_ALLOWED` in `account-management/core/faculty-first-login.php`). Accounts that existed before this feature keep the default `0` and are never locked. Faculty passwords cannot be reset by an admin, so the generated password is shown on the "Account created" card until the Super Admin presses Done.

### Security rules to keep
- A Content-Security-Policy is set on every page (with a nonce for inline scripts) or by `config/security-headers.php`. Avoid inline `style=""` and inline event handlers in new code.
- Every state-changing request must check the CSRF token (`csrf_verify()` or the `X-CSRF-TOKEN` header).
- Use prepared statements (`bind_param`) for anything that touches user input.
- Never commit `.env` or `config/db.php` (both are in `.gitignore`).

### Deployment
`.github/workflows/deploy.yml` uploads the project to InfinityFree over FTP on every push to `main` (it uses the `FTP_HOST`, `FTP_USER` and `FTP_PASS` repository secrets). It skips `.env`, `config/db.php` and `database/`, so after the first deploy create those on the host by hand. There is no staging site: treat `main` as live.