-- PUPSync — scheduled equipment borrowing (Today / Later bookings)
-- ---------------------------------------------------------------------------
-- The app adds these columns automatically the first time a dashboard loads
-- (BookingSchedule::ensureSchema), so running this file is OPTIONAL — it is here
-- for deployments where the web user may not ALTER tables.
-- Safe to run more than once (needs MariaDB 10.0+ / MySQL 8.0.29+ for IF NOT EXISTS).

ALTER TABLE `tbl_requests`
  ADD COLUMN IF NOT EXISTS `borrow_time`   TIME        NULL DEFAULT NULL AFTER `borrow_date`,
  ADD COLUMN IF NOT EXISTS `return_time`   TIME        NULL DEFAULT NULL AFTER `return_date`,
  ADD COLUMN IF NOT EXISTS `borrow_qty`    INT         NOT NULL DEFAULT 1,
  ADD COLUMN IF NOT EXISTS `room_id`       INT         NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `booking_mode`  VARCHAR(10) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `stock_applied` TINYINT(1)  NOT NULL DEFAULT 1;
