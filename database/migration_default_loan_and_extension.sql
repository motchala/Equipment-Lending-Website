-- PUPSync: new defaults for loan length and extensions
--
-- Longest loan  = 3 days  (faculty can borrow for 1 to 3 days)
-- Extensions    = 1 per loan (an extension adds up to 5 hours, or 1 day for a day loan)
--
-- lending_db.sql already ships with these values, so a FRESH import needs nothing.
-- Run this ONCE on an EXISTING database to move it to the same defaults.
-- (The same two values can also be changed in Settings > Arbitration.)

UPDATE tbl_arbitration_config SET config_value = '3' WHERE config_key = 'max_borrow_days';
UPDATE tbl_arbitration_config SET config_value = '1' WHERE config_key = 'max_extensions';
