-- ---------------------------------------------------------------------------
-- PUPSync: set the operating hours to 7:00 AM - 8:00 PM for BOTH equipment and rooms.
--
-- Run this ONCE in phpMyAdmin (SQL tab) on the LIVE database. It is safe to run again.
-- It only touches the four time settings below; every other setting (room open days,
-- booking windows, durations, ...) is left exactly as it is.
--
-- Same result as opening Settings > Arbitration and saving:
--   Equipment booking hours  7:00 AM - 8:00 PM
--   Room schedule hours      7:00 AM - 8:00 PM
-- ---------------------------------------------------------------------------

UPDATE tbl_arbitration_config SET config_value = '07:00' WHERE config_key = 'equip_day_start';
UPDATE tbl_arbitration_config SET config_value = '20:00' WHERE config_key = 'equip_day_end';
UPDATE tbl_arbitration_config SET config_value = '07:00' WHERE config_key = 'room_day_start';
UPDATE tbl_arbitration_config SET config_value = '20:00' WHERE config_key = 'room_day_end';

-- Any of the four keys that were never saved (the system was using its built-in default) are added.
INSERT INTO tbl_arbitration_config (config_key, config_value)
SELECT v.k, v.v FROM (
    SELECT 'equip_day_start' AS k, '07:00' AS v
    UNION ALL SELECT 'equip_day_end', '20:00'
    UNION ALL SELECT 'room_day_start', '07:00'
    UNION ALL SELECT 'room_day_end', '20:00'
) AS v
WHERE NOT EXISTS (SELECT 1 FROM tbl_arbitration_config c WHERE c.config_key = v.k);

-- Check: should list four rows with 07:00 / 20:00
SELECT config_key, config_value FROM tbl_arbitration_config
WHERE config_key IN ('equip_day_start','equip_day_end','room_day_start','room_day_end')
ORDER BY config_key;
