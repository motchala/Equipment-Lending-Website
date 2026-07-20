<?php
/**
 * room-reservation/core/room-cancellation.php
 * RoomCancellation — shared cancellation + waitlist-notification logic.
 *
 * Called by:
 *   room-reservation/api/cancel-reservation.php  (faculty & admin paths)
 *
 * Dependencies: $conn (mysqli) must be provided by the caller.
 *   mailer.php is required internally when waitlist entries exist.
 *
 * Depth: 2  →  config prefix: __DIR__ . '/../../…'
 */

class RoomCancellation
{
    /**
     * Cancel an Approved reservation and blast waitlist notifications.
     *
     * @param mysqli $conn        Active DB connection
     * @param int    $reservationId
     * @param string $actor       'faculty' | 'admin'
     * @return array  ['success'=>bool, 'error'=>string|null]
     */
    public static function cancel(mysqli $conn, int $reservationId, string $actor): array
    {
        date_default_timezone_set('Asia/Manila');

        // ── Fetch the reservation ────────────────────────────────────────
        $stmt = $conn->prepare(
            "SELECT rr.id, rr.room_id, rr.faculty_id, rr.faculty_name,
                    rr.reservation_date, rr.start_time, rr.end_time,
                    rr.status,
                    r.room_name
               FROM tbl_room_reservations rr
               JOIN tbl_rooms r ON r.room_id = rr.room_id
              WHERE rr.id = ?
              LIMIT 1"
        );
        $stmt->bind_param('i', $reservationId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return ['success' => false, 'error' => 'Reservation not found.'];
        }
        if ($row['status'] !== 'Approved') {
            return ['success' => false, 'error' => 'Only Approved reservations can be cancelled.'];
        }

        // ── 1-hour cutoff enforcement ───────────────────────────────────
        $resStart = new DateTime(
            $row['reservation_date'] . ' ' . $row['start_time'],
            new DateTimeZone('Asia/Manila')
        );
        $now      = new DateTime('now', new DateTimeZone('Asia/Manila'));
        $diffSecs = $resStart->getTimestamp() - $now->getTimestamp();

        if ($diffSecs <= 3600) {
            return [
                'success' => false,
                'error'   => 'Cancellations are not allowed within 1 hour of the reservation start time.',
            ];
        }

        // ── Mark Cancelled ───────────────────────────────────────────────
        $upd = $conn->prepare(
            "UPDATE tbl_room_reservations
                SET status = 'Cancelled', cancelled_at = NOW()
              WHERE id = ? AND status = 'Approved'"
        );
        $upd->bind_param('i', $reservationId);
        $upd->execute();
        $affected = $upd->affected_rows;
        $upd->close();

        if ($affected === 0) {
            // Race condition: already cancelled by another request
            return ['success' => false, 'error' => 'Reservation could not be cancelled. It may have already been processed.'];
        }

        // ── If admin cancelled, notify the reservation owner ─────────────
        if ($actor === 'admin') {
            self::notifyOwner($conn, $row);
        }

        // ── Notify all waitlisted faculty for this slot ──────────────────
        self::notifyWaitlist(
            $conn,
            (int) $row['room_id'],
            $row['reservation_date'],
            $row['start_time'],
            $row['end_time'],
            $row['room_name']
        );

        return ['success' => true, 'error' => null];
    }

    /**
     * Email the original reservation owner that admin cancelled their booking.
     * Only called when $actor === 'admin'.
     */
    private static function notifyOwner(mysqli $conn, array $row): void
    {
        $fac = $conn->prepare(
            "SELECT email FROM tbl_users WHERE faculty_id = ? LIMIT 1"
        );
        $fac->bind_param('s', $row['faculty_id']);
        $fac->execute();
        $facRow = $fac->get_result()->fetch_assoc();
        $fac->close();

        if (!$facRow || empty($facRow['email'])) return;

        require_once __DIR__ . '/../../equipment-booking/core/mailer.php';

        $dateDisplay  = date('F j, Y', strtotime($row['reservation_date']));
        $startDisplay = date('g:i A',  strtotime('1970-01-01 ' . $row['start_time']));
        $endDisplay   = date('g:i A',  strtotime('1970-01-01 ' . $row['end_time']));
        $roomName     = htmlspecialchars($row['room_name']);
        $facultyName  = htmlspecialchars($row['faculty_name']);

        $subject  = 'PUPSync: Your Room Reservation Was Cancelled — ' . $row['room_name'];
        $bodyHtml = '
<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">
  <div style="background:#800000;padding:24px 28px;">
    <h2 style="color:#fff;margin:0;font-size:1.2rem;">PUPSync &middot; Reservation Cancelled by Admin</h2>
  </div>
  <div style="padding:28px;">
    <p style="margin:0 0 16px;color:#333;font-size:.95rem;">Hello <strong>' . $facultyName . '</strong>,</p>
    <p style="margin:0 0 20px;color:#333;font-size:.95rem;">
      An administrator has cancelled your room reservation. If you have questions, please contact the admin office.
    </p>
    <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
      <tr style="background:#f9f5f5;">
        <td style="padding:10px 14px;color:#666;width:40%;">Room</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . $roomName . '</td>
      </tr>
      <tr>
        <td style="padding:10px 14px;color:#666;">Date</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($dateDisplay) . '</td>
      </tr>
      <tr style="background:#f9f5f5;">
        <td style="padding:10px 14px;color:#666;">Time</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($startDisplay) . ' &ndash; ' . htmlspecialchars($endDisplay) . '</td>
      </tr>
      <tr>
        <td style="padding:10px 14px;color:#666;">Status</td>
        <td style="padding:10px 14px;color:#c62828;font-weight:700;">&#10007; Cancelled by Admin</td>
      </tr>
    </table>
  </div>
  <div style="background:#f5f5f5;padding:14px 28px;font-size:.75rem;color:#aaa;">
    PUPSync &middot; Polytechnic University of the Philippines &ndash; Bi&ntilde;an Campus
  </div>
</div>';

        sendPupSyncEmail($facRow['email'], $row['faculty_name'], $subject, $bodyHtml);
    }

    /**
     * Query tbl_room_waitlist for the exact slot and email every entry.
     * Waitlist rows are NOT deleted — they remain as an audit trail.
     */
    public static function notifyWaitlist(
        mysqli $conn,
        int    $roomId,
        string $resDate,
        string $startTime,
        string $endTime,
        string $roomName
    ): void {
        $stmt = $conn->prepare(
            "SELECT faculty_id, faculty_name, faculty_email
               FROM tbl_room_waitlist
              WHERE room_id          = ?
                AND reservation_date = ?
                AND start_time       = ?
                AND end_time         = ?
              ORDER BY created_at ASC"
        );
        $stmt->bind_param('isss', $roomId, $resDate, $startTime, $endTime);
        $stmt->execute();
        $result = $stmt->get_result();
        $entries = [];
        while ($r = $result->fetch_assoc()) {
            $entries[] = $r;
        }
        $stmt->close();

        if (empty($entries)) return;

        require_once __DIR__ . '/../../equipment-booking/core/mailer.php';

        $dateDisplay  = date('F j, Y', strtotime($resDate));
        $startDisplay = date('g:i A',  strtotime('1970-01-01 ' . $startTime));
        $endDisplay   = date('g:i A',  strtotime('1970-01-01 ' . $endTime));
        $roomNameEsc  = htmlspecialchars($roomName);

        foreach ($entries as $entry) {
            $recipientName = htmlspecialchars($entry['faculty_name']);
            $subject       = 'PUPSync: ' . $roomName . ' is Now Available — ' . $dateDisplay;
            $bodyHtml      = '
<div style="font-family:Arial,sans-serif;max-width:520px;margin:0 auto;border:1px solid #e0e0e0;border-radius:12px;overflow:hidden;">
  <div style="background:#800000;padding:24px 28px;">
    <h2 style="color:#fff;margin:0;font-size:1.2rem;">PUPSync &middot; Room Slot Available</h2>
  </div>
  <div style="padding:28px;">
    <p style="margin:0 0 16px;color:#333;font-size:.95rem;">Hello <strong>' . $recipientName . '</strong>,</p>
    <p style="margin:0 0 20px;color:#333;font-size:.95rem;">
      A room slot you were watching has just opened up. You can now submit a reservation request — it will go through the normal approval process.
    </p>
    <table style="width:100%;border-collapse:collapse;font-size:.9rem;">
      <tr style="background:#f9f5f5;">
        <td style="padding:10px 14px;color:#666;width:40%;">Room</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . $roomNameEsc . '</td>
      </tr>
      <tr>
        <td style="padding:10px 14px;color:#666;">Date</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($dateDisplay) . '</td>
      </tr>
      <tr style="background:#f9f5f5;">
        <td style="padding:10px 14px;color:#666;">Time</td>
        <td style="padding:10px 14px;color:#222;font-weight:700;">' . htmlspecialchars($startDisplay) . ' &ndash; ' . htmlspecialchars($endDisplay) . '</td>
      </tr>
    </table>
    <p style="margin:20px 0 0;color:#555;font-size:.85rem;">
      Note: This notification does not reserve the room for you. Please log in to PUPSync and submit a reservation request as usual.
    </p>
  </div>
  <div style="background:#f5f5f5;padding:14px 28px;font-size:.75rem;color:#aaa;">
    PUPSync &middot; Polytechnic University of the Philippines &ndash; Bi&ntilde;an Campus
  </div>
</div>';

            sendPupSyncEmail($entry['faculty_email'], $entry['faculty_name'], $subject, $bodyHtml);
        }
    }
}
