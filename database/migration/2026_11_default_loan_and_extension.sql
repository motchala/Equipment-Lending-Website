-- Migration: 2026-11
-- Sets default values for max loan days and extensions per loan
-- in tbl_arbitration_config.
-- lending_db.sql already ships with these values.
-- Run ONCE on any existing database created before November 2026.
-- Safe to run multiple times (UPDATE is idempotent).

UPDATE tbl_arbitration_config SET config_value = '3' WHERE config_key = 'max_borrow_days';
UPDATE tbl_arbitration_config SET config_value = '1' WHERE config_key = 'max_extensions';
