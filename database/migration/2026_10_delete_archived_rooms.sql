-- ============================================================================
-- One-time clean-up: permanently delete every ARCHIVED room (is_archived = 1)
-- ----------------------------------------------------------------------------
-- Run this once against your existing database (phpMyAdmin > SQL tab).
-- It only touches rooms that are currently archived. Active rooms are never
-- deleted. The delete cannot be undone, so back up the database first.
--
-- STEP 1 - preview what will be deleted (read-only, run this first)
-- STEP 2 - the delete itself (run after you are happy with the preview)
-- ============================================================================


-- ---------------------------------------------------------------------------
-- STEP 1 - PREVIEW
-- ---------------------------------------------------------------------------
SELECT r.room_id, b.name AS building, r.floor_number, r.room_name,
       (SELECT COUNT(*) FROM tbl_room_reservations x WHERE x.room_id = r.room_id) AS reservations,
       (SELECT COUNT(*) FROM tbl_room_issues       x WHERE x.room_id = r.room_id) AS issues,
       (SELECT COUNT(*) FROM tbl_room_waitlist     x WHERE x.room_id = r.room_id) AS waitlist
  FROM tbl_rooms r
  JOIN tbl_buildings b ON b.building_id = r.building_id
 WHERE r.is_archived = 1
 ORDER BY b.building_id, r.floor_number, r.room_name;


-- ---------------------------------------------------------------------------
-- STEP 2 - DELETE
-- Anything that points at these rooms is removed first (the database will not
-- allow a room to be deleted while reservations, issues or waitlist entries
-- still reference it), then the rooms themselves.
-- ---------------------------------------------------------------------------
START TRANSACTION;

CREATE TEMPORARY TABLE tmp_rooms_to_delete AS
    SELECT room_id, building_id FROM tbl_rooms WHERE is_archived = 1;

DELETE FROM tbl_room_waitlist         WHERE room_id IN (SELECT room_id FROM tmp_rooms_to_delete);
DELETE FROM tbl_room_issues           WHERE room_id IN (SELECT room_id FROM tmp_rooms_to_delete);
DELETE FROM tbl_room_reservations     WHERE room_id IN (SELECT room_id FROM tmp_rooms_to_delete);
DELETE FROM tbl_room_arbitration_log  WHERE room_id IN (SELECT room_id FROM tmp_rooms_to_delete);

-- Equipment requests that were linked to one of these rooms are kept, just unlinked.
UPDATE tbl_requests SET room_id = NULL WHERE room_id IN (SELECT room_id FROM tmp_rooms_to_delete);

DELETE FROM tbl_rooms WHERE is_archived = 1;

-- A building's floor count follows its highest remaining floor with rooms
-- (only for buildings that just lost rooms).
UPDATE tbl_buildings b
   SET b.floor_count = COALESCE(
         (SELECT MAX(r.floor_number) FROM tbl_rooms r
           WHERE r.building_id = b.building_id AND r.is_archived = 0),
         b.floor_count)
 WHERE b.building_id IN (SELECT building_id FROM tmp_rooms_to_delete);

DROP TEMPORARY TABLE tmp_rooms_to_delete;

COMMIT;

-- Check: this should now return 0 rows.
SELECT room_id, room_name FROM tbl_rooms WHERE is_archived = 1;
