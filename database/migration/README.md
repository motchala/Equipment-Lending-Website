# Database Migrations

## Who needs to run these

**Fresh setup** — import `lending_db.sql` directly. You do not need any migration files.  
`lending_db.sql` always reflects the current schema and default data.

**Existing database** — run only the migrations dated after your last DB snapshot, in filename order (oldest first).

---

## How to run a migration

1. Open phpMyAdmin → select `lending_db` → click the **SQL** tab
2. Paste the contents of the migration file
3. Click **Go**
4. Mark it as applied in the table below

---

## Migration log

| File | What it does | Schema change | Applied locally | Applied on production |
|------|-------------|---------------|-----------------|----------------------|
| `2026_10_borrow_scheduling.sql` | Adds `borrow_time`, `return_time`, `borrow_qty`, `room_id`, `booking_mode`, `stock_applied` columns to `tbl_requests` | Yes | ✓ | ✓ |
| `2026_11_default_loan_and_extension.sql` | Sets default `max_borrow_days = 3` and `max_extensions = 1` in `tbl_arbitration_config` | No | ✓ | ✓ |
| `2026_11_condition_from_qty.sql` | Reconciles `tbl_inventory.condition` with the quantity-based derivation rule (0=For Repair, 1–3=Fair, 4+=Good) | No | — | — |

---

## Rules for this folder

- **Never delete a migration file** after running it — it is the permanent audit trail
- **Never edit a migration file** after it has been applied to any environment
- If a migration needs to be corrected, create a new file that undoes or adjusts it
- File naming convention: `YYYY_MM_description.sql`
