-- Migration: 2026-11-27
-- Reconciles tbl_inventory.condition with the quantity-based derivation rule
-- introduced in the equipment inventory improvements session.
--
-- Rule:
--   quantity = 0        → For Repair
--   quantity 1–3        → Fair
--   quantity 4+         → Good
--
-- lending_db.sql already reflects this rule for fresh imports.
-- Run ONCE on any existing database that was set up before this rule was introduced.
-- Safe to run multiple times (UPDATE is idempotent).

UPDATE tbl_inventory
SET `condition` = CASE
    WHEN quantity = 0  THEN 'For Repair'
    WHEN quantity <= 3 THEN 'Fair'
    ELSE                    'Good'
END
WHERE is_archived = 0;
