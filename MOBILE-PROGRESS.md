# PUPSync — Super Admin / Admin mobile view: progress note

Resume here in every new pass. Keep this file up to date.

## Decisions (agreed with the project owner)
- Phone view is a separate layer, same pattern as the Faculty portal: `html.rx-mobile`
  (real phone — also in landscape — or window <= 600px). Tablets/desktops keep today's layout.
- **On phone:** Dashboard (simplified, with Scan QR), Activity, Equipment (Loans, Catalog, Archived),
  Facilities (Facilities, Reservations, Archived), Settings (My Account, Preferences, Help & FAQ), Notifications.
- **Desktop only:** Faculty, Admins, Imports, Reports; Settings > Arbitration / Data / Service Ratings;
  Activity Export; adding equipment and facilities (Edit stays on phone); Delete All / permanent delete.
- Super Admin and Admin share the same phone UI; only the "desktop-only" list differs.
- Light and Dark only (High Contrast was already removed from admin-dashboard.js).
- Help & FAQ: hide/adjust entries about desktop-only features (done in Pass 6).
- QR scanner needs HTTPS on phones: show a clear "secure connection needed" message (Pass 2).
- No in-app "desktop view" button: text only (banner + account sheet list; tell users to use the browser's "Desktop site").
- Home-screen install / orientation lock / push: LATER, not in this scope.
- Dashboard must be simplified for phone (KPIs + Needs attention first, chart simplified at the bottom).
- After the build, expect small UI follow-ups from the owner (desktop changes / new additions) to port to phone.

## Architecture
- `dashboard/assets/css/admin-dashboard-mobile.css` — all phone styles, scoped to `html.rx-mobile`, loaded LAST.
  Colours use admin theme variables (`--accent-maroon`, `--card-bg`, ...), so light/dark/accent work.
- `dashboard/assets/js/admin-dashboard-mobile.js` — builds top bar, bottom tab bar, bottom sheet, desktop banner.
  Never re-implements logic: it clicks existing controls (hidden sidebar `.nav-item[data-tab]`, `#dashNotifBtn`, `#snav-logout`).
- `shared/views/admin-shell-top.php` — phone detection script (first in head), refresh-restore never lands a phone
  on a desktop-only screen, links the mobile CSS.
- `shared/views/admin-shell-scripts.php` — loads the mobile JS (defer).
- `dashboard/assets/js/admin-dashboard.js` — ONE guard: `_switchTabDOM` and `switchSettMainTab` redirect desktop-only
  screens on phones (constants `PS_DESKTOP_ONLY_TABS` / `PS_DESKTOP_ONLY_SETT`). Convenience, not security.
- Reusable helpers for later passes: class `.rxm-desktop-only` (hidden on phone), `.ps-modal` already becomes a bottom sheet,
  `window.psDesktopOnlyNotice(what)`.

## Pass status
| Pass | Scope | Status |
|---|---|---|
| 1 | Foundation: detection, guard, restore handling, top bar, tab bar, account sheet, desktop notice, shared rules | DONE (tested on a stand-in page) |
| 2 | Simplified Dashboard, notifications modal, QR scanner (+ HTTPS message) | DONE (33 checks on stand-in page) |
| 3 | Equipment > Loans + approve/decline/claim/return/overdue/override modals | DONE (29 checks on stand-in page with the REAL Loans markup and modals) |
| 4 | Equipment > Catalog, Edit (no Add), Archived | DONE (45 checks on stand-in page with the REAL catalog/archived markup + edit dialog) |
| 5 | Facilities: rooms, reservations, Archived, Edit (no Add), issue review, room schedule | DONE (59 checks on stand-in page with the REAL Facilities screen + modals) |
| 6 | Activity (no Export), Settings (Account, Preferences, Help & FAQ edits) | DONE (87 checks on stand-in page with the REAL Activity + Settings markup, both roles, light/dark, landscape, tablet/desktop unchanged) |
| 7 | QA: both roles, light/dark, landscape, refresh restore, tablet/desktop unchanged, owner feedback | DONE (see Pass 7 notes) |

## Testing without the database
The sandbox has PHP but no MySQL, so the real pages cannot run. Pass 1 was tested with a stand-in page that renders the
REAL `admin-shell-top.php` plus simplified panels (scratchpad `harness/`, not part of the project) and headless Chromium:
Pass 1: 52/55; the 3 misses come from the stand-in page itself (its Settings panel ends up nested inside the Dashboard after navigating), not the app. Pass 2: 33/33.
For Pass 2+ real markup/data is needed: ask the owner for `snap-super.html` / `snap-admin.html`
(log in on XAMPP, open admin-dashboard.php, Ctrl+S "Webpage, HTML only", save in the project folder).

## Notes / gotchas found
- All panels live in ONE page (`admin-dashboard.php`); `#app-main` is the scroll container (not the window).
- Dashboard (`#dashLive`) and tables are re-rendered every ~8s: phone enhancements must survive a re-render (observe, don't one-shot).
- `html[data-ps-tab]` / `data-ps-sub` is the server's first-paint bridge after F5; the head script drops it for desktop-only screens on phones.
- Existing `<=768px` rules in admin-dashboard.css / -responsive.css still apply on phones; the mobile file overrides them.
- Equipment edit already uses a modal (`#eq-edit-modal`); the Add form is `#item-form-wrap`.
- Rooms: Add and Edit share `#room-form-wrap`; on phone hide Add (`#addRoomBtn`, empty-state add prompts), keep Edit.
- Camera features need HTTPS (or localhost); XAMPP over `http://192.168.x.x` will block the QR scanner on phones.
- Pass 2: phone Dashboard = stat tiles, Needs attention, Latest requests (5), Facilities (3), Stock watch (4); trend chart / top items / activity log sit behind a
  "Show trends & recent activity" button (`#rxmMore`, class `rxm-more` on `#dash`, survives the live refresh).
- Pass 2: Notifications = tall bottom sheet; QR scanner = full screen with a 48px close button. Without HTTPS it says "The camera needs a secure connection...".
- REAL-DEVICE CHECK: in the stand-in page the QR scanner would not re-open after switching tabs and back (also with the ORIGINAL JS, on desktop), so it is a
  stand-in-page quirk. Confirm on the real app: open Scan QR, close, switch tabs, come back, open it again.
- Pass 3: Equipment sub-tabs (`#eqTabs`) scroll sideways. Each loan is one card (grid on the `tr`); labels per status are written in CSS
  (`tr[data-loan-state=...] td[data-col=colN]::before`), status pill is `td[data-col=borrower]::after` keyed on `tr.is-<state>`.
  The desktop column widths (`#eq-loan-table td:nth-child(n){width}`) must be reset with !important on phone.
- Pass 3: ALL `.ps-modal` dialogs get a grab handle, 16px fields, sticky full-width stacked buttons (main action on top). Side drawers
  (`.ps-drawer`: loan details, request details) are tall bottom sheets. Toasts move to the top while a dialog is open.
- Stand-in page for Pass 3 includes the real `admin-equipment-modals*.php` and the real Loans block (lines of admin-equipment-screen.php) with fake rows.
  Bundle-approve and override dialogs use the same `.ps-modal` classes (covered by the shared rules; confirm on a real device).
- Pass 4: Add form `#item-form-wrap` hidden on phone; catalog = one card per item (photo, name, added date, edit button top-right, category/condition/stock on one line);
  Edit = the existing `#eq-edit-modal` bottom sheet ("Tap to choose a photo" hint, full-width Archive Item); Archived = cards with Restore only
  (`.pr-tbl-btn.danger` "Delete permanently" hidden, note text rewritten via ::after). Catalog empty-state text rewritten ("added from a desktop").
- Stand-in page: the edit dialog needs `equipment-booking/assets/js/admin-equipment-1.js` (loaded by the real page); category dropdown is empty there (no DB) -
  confirm on a real device that the category shows the item's category.
- Pass 5: Facilities sub-tabs scroll sideways; tiles keep the existing floor-grouped look (44px edit button). Add Facility button hidden (section 6),
  empty text rewritten. Edit = the existing `#room-form-wrap` turned into a bottom sheet (Lock/Archive buttons full width; Archive is super-admin only, as before).
  Reservations = one card per row (facility + status pill, when | requested by, full-width Reschedule/Cancel/Review); `#rr-wipe-btn` (Delete All) hidden.
  Archived = cards with Restore only (`.pr-tbl-btn.danger` hidden, note rewritten). Review = `#ps-room-drawer` bottom sheet (shared drawer rule).
  Cancel / Reschedule / Issue review use the OLDER dialog markup (`.modal-overlay` + `.eq-card.form-card`, inline styles) -> bottom sheets via !important overrides.
  Facility schedule `#roomScheduleModal` = full screen; its own <=768px day-tab layout (one day at a time) is kept.
- Stand-in page: uses the real admin-rooms-screen.php, admin-rooms-modals*.php and room JS with fake rows (no DB). The schedule's "couldn't refresh" note there is
  only because the stand-in has no server. Confirm on a real device: archive/restore, lock/unlock, approve/decline (needs the DB).
- Pass 6: Activity = chips scroll sideways on one row, Who/When selects side by side (44px, 16px font), timeline rows with a 36px icon, who + time on one line,
  36px "View request #N" pill, compact pager. Export hidden (section 6).
  Settings = 3 sub-tabs. My Account: hero stacks (Edit / Save / Cancel are full-width 48px; desktop pins them in a fixed 244x44 box, so section 15 makes them static),
  edit mode puts the real inputs in the flow (48px, 16px font), the "Change" password button only exists while editing. Change Password dialog (`#changePassModal`,
  older inline-styled dialog) = bottom sheet with stacked full-width buttons (Update on top). Preferences = Light / Dark only, 52px buttons, notification rows 44px+, full-width Reset.
  Help & FAQ: markup in `settings/views/admin-screen.php` now carries two helper classes: `rxm-desktop-only` (hidden on phone, already existed) and `rxm-phone-only`
  (shown on phone only; section 15 hides it everywhere else). Used for: wording of "add/edit/archive equipment" and "add/lock/archive facility", the export and permanent-delete
  sentences, the four Super Admin entries (faculty, admins, imports, clear all) and a new phone-only entry "What is only available on a desktop?" (role-aware list).
  THIS FILE (`settings/views/admin-screen.php`) IS THE 7th CHANGED FILE. If you edit Help & FAQ on desktop later, keep those spans.
- Known small leftover: when the Activity list is capped, the pager text says "narrow the filters or Export for older activity" (text built in JS); only shows for very long histories.
- Stand-in page: real activity/views/admin-screen.php and settings/views/admin-screen.php with the DB-bound includes replaced by fake data and a fake Activity JSON answer.
  Confirm on a real device: Activity filters + paging with real data, profile save, change password, notification toggles saving, Log Out All.

## Pass 7 (QA) — result
- Static: PHP lint + JS syntax OK. Every selector in the mobile CSS is scoped to `html.rx-mobile` (or `html:not(.rx-mobile)` for the phone-only helper),
  so tablets and desktops (>= 601px, non-phone) are untouched. Tested: iPad and 1366px desktop show no phone bar / tab bar / notice, Export + all
  Settings tabs + full Help wording still there; 600px window = phone, 601px = desktop.
- Sweep (stand-in page, REAL markup): 11 screens x Super Admin / Admin x 320, 390, 430 px and landscape 844x390 x light / dark = 176 screen checks:
  no sideways overflow, no desktop-only control visible, no script errors, every navigation worked.
- Refresh restore (50 checks): F5 returns to the same tab/sub-tab; a leftover Faculty / Admins / Imports / Reports / Arbitration / Data / Ratings state, a `?tab=`
  link, `#reports`, or a forced click never opens a desktop-only screen on a phone; desktop still restores Faculty.
- Polish fixed in Pass 7: tap targets (loan "#id" links, facility chips, expiry-banner X, notice link), low-contrast small text (amber KPI, Pending/Scheduled pills,
  dark count bubbles), icon words can no longer spill over neighbours if the icon font is slow, the open sub-tab scrolls into view in sideways tab strips.
- Stand-in-page only (not app bugs): t1 "bar badge shows 2" expects the wrong fake number; QR scanner does not re-open after navigation there.

## Porting later UI changes (the owner will ask for these)
1. Desktop-only change (new column, tweak) -> check whether the phone CSS already turns that table/panel into cards (sections 11, 13, 14, 15); add the label via `::before` content.
2. New button/screen -> if it is desktop-only, give it `.rxm-desktop-only`; otherwise make it 44px tall, 16px inputs, and stack it.
3. New modal -> `.ps-modal` becomes a bottom sheet automatically; old `.modal-overlay` dialogs need the section 12 / `#changePassModal` treatment.
4. New desktop-only tab -> add it to `PS_DESKTOP_ONLY_TABS` / `PS_DESKTOP_ONLY_SETT` in admin-dashboard.js, the head script in admin-shell-top.php, and section 6.
5. Run the sweep again (scratchpad `harness/t17.js`, not in the project) or just check light + dark on a real phone.

## Real-device checklist (cannot be tested without the database)
QR scanner (HTTPS) and re-opening it after switching tabs; approve / decline / bundle approve / override; archive / restore / lock / unlock;
category list in the equipment edit dialog; Activity filters + paging with real data; save profile; change password; notification toggles saving; Log Out All;
refresh on each screen; rotate the phone; Light / Dark switch; open on a tablet and desktop and confirm nothing changed.

## Merge into the newer project folder (Equipment-Lending-Website-Rosales, 2026-11-20)
- The owner's newer folder had changed 115 files since the first zip. The 4 modified files (`admin-shell-top.php`, `admin-shell-scripts.php`, `admin-dashboard.js`,
  `settings/views/admin-screen.php`) were 3-way merged (no conflicts); the 3 new files were copied. Nothing else in the project was touched.
- New desktop features that were checked/ported on the phone (CSS section 16 + tests `t20`):
  - Decline-reason picker chips (equipment decline + facility reservation decline): 44px chips, 16px "Other" box, both dialogs are bottom sheets.
  - Same-day request buffer: "Auto-decides in m:ss" chip inside the Loans card, "Review window . m:ss left" line in the Facilities card; both tick live.
  - "Pick-up: <student>" line in a Loans card and "Pick-up by (student)" in the loan / request detail sheets.
  - Approve dialog no longer has a reason box; the reservation drawer footer is Close / Decline / Approve (full-width buttons).
  - Loans cards survive the new 2-line date cells (`.eq-when`) and the live table refresh (`psRefreshLoansTable` swaps the rows, the cards are pure CSS).
  - The thin refresh bar (tap the tab you are on) sits under the phone status area.
- Desktop-only upstream additions (nothing to do): arbitration rules, faculty password generator, Settings > Data.
- Checked: all earlier suites + new ones pass on the merged project (t1 55, t5 33, t12 28, t13 45, t15 59, t16 87, t18 50, t19 3, t20 33) and the 176-screen sweep.


## Fix (2026-11-21): Facilities search box
Desktop sets `flex: 0 1 280px` on `.pr-search-wrap`; in the stacked phone header that became a 280px-tall box with the magnifier floating mid-air. Phone CSS (section 8, Facilities) now sizes it to 44px and pins the icon inside the input.

## Change (2026-11-21): Curved bottom tab bar
Section 3 now matches the Faculty phone view: page-coloured bar, 20px rounded top corners, soft maroon lift (deeper black shadow in dark theme). Bottom padding is safe-area + 8px.
