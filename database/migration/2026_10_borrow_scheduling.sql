-- Migration: 2026-10
-- Adds scheduling columns to tbl_requests for the BookingSchedule feature.
-- lending_db.sql already ships with these columns.
-- Run ONCE on any existing database created before October 2026.
-- Safe to run multiple times (ALTER IGNORE / column existence checks).

ALTER TABLE tbl_requests
    ADD COLUMN IF NOT EXISTS borrow_time   DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS return_time   DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS borrow_qty    INT          NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS room_id       INT          NULL,
    ADD COLUMN IF NOT EXISTS booking_mode  VARCHAR(10)  NOT NULL DEFAULT 'today',
    ADD COLUMN IF NOT EXISTS stock_applied TINYINT(1)   NOT NULL DEFAULT 0;
