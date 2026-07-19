<?php
declare(strict_types=1);

/**
 * arbitration-engine.php
 *
 * AI-Driven Request Arbitration Engine for PUPSYNC.
 *
 * Replaces the legacy processAutoApprove() function with a deterministic,
 * rule-based scoring engine that evaluates every new borrow request
 * automatically within the same HTTP request lifecycle.
 *
 * Entry point: ArbitrationEngine::process($conn, $request_id)
 *
 * Decision flow:
 *  1.  Load config from tbl_arbitration_config
 *  2.  Fetch request + borrower data
 *  3.  Pre-flight: overdue block
 *  4.  Pre-flight: duplicate block
 *  5.  Document check (missing doc for Adviser → hold as Waiting)
 *  6.  Archived item check
 *  7.  Stock check (quantity = 0 → Declined)
 *  8.  Acquire stock lock (BEGIN + SELECT FOR UPDATE)
 *  9.  FIFO + tie-break scoring (Signatory, Role, Return History, ID)
 *  10. UPDATE tbl_requests.status
 *  11. Decrement tbl_inventory.quantity (if Approved)
 *  12. Cascade-decline remaining Waiting requests (if stock hits 0)
 *  13. INSERT into tbl_arbitration_log
 */

date_default_timezone_set('Asia/Manila');

class ArbitrationEngine
{
    // ── Rule identifier constants ────────────────────────────────────────────

    public const RULE_OVERDUE_BLOCK    = 'rule_overdue_block';
    public const RULE_DUPLICATE_BLOCK  = 'rule_duplicate_block';
    public const RULE_MISSING_DOC_HOLD = 'rule_missing_doc_hold';
    public const RULE_ARCHIVED         = 'rule_archived';
    public const RULE_OUT_OF_STOCK     = 'rule_out_of_stock';
    public const RULE_PAST_DATETIME    = 'rule_past_datetime';
    public const RULE_1_FIFO           = 'rule_1_fifo';
    public const RULE_2_SIGNATORY      = 'rule_2_signatory';
    public const RULE_3_ROLE           = 'rule_3_role';
    public const RULE_4_RETURN_HISTORY = 'rule_4_return_history';
    public const RULE_5_ID_ORDER       = 'rule_5_id_order';
    public const RULE_OVERRIDE         = 'override';
    public const RULE_ERROR            = 'arbitration_error';

    // ── Public entry point ───────────────────────────────────────────────────

    /**
     * Evaluate a single borrow request and write the decision.
     *
     * Called synchronously from faculty-dashboard.php immediately after a
     * new tbl_requests row is inserted (status = 'Waiting').
     *
     * @param mysqli $conn       Active DB connection
     * @param int    $request_id PK of the tbl_requests row to evaluate
     */
    public static function process(mysqli $conn, int $request_id): void
    {
        $transaction_open = false;

        try {
            // ── Step 1: Load config ──────────────────────────────────────────
            $config = self::loadConfig($conn);

            // ── Step 2: Fetch request ────────────────────────────────────────
            $request = self::fetchRequest($conn, $request_id);

            if ($request === null) {
                // Request not found — nothing to process.
                error_log('ArbitrationEngine: fetchRequest returned null for request_id=' . $request_id);
                return;
            }

            $faculty_id     = (string)$request['faculty_id'];
            $equipment_name = (string)$request['equipment_name'];

            // ── Step 3: Overdue block check ──────────────────────────────────
            if (($config['rule_overdue_block_enabled'] ?? '0') === '1') {
                if (self::checkOverdueBlock($conn, $faculty_id)) {
                    self::writeDecision(
                        $conn,
                        $request_id,
                        'Declined',
                        self::RULE_OVERDUE_BLOCK,
                        'You have an overdue item. Please return it before borrowing again.'
                    );
                    return;
                }
            }

            // ── Step 4: Duplicate block check ────────────────────────────────
            if (($config['rule_duplicate_block_enabled'] ?? '0') === '1') {
                if (self::checkDuplicateBlock($conn, $faculty_id, $equipment_name, $request_id)) {
                    self::writeDecision(
                        $conn,
                        $request_id,
                        'Declined',
                        self::RULE_DUPLICATE_BLOCK,
                        'You already have an active request for this equipment.'
                    );
                    return;
                }
            }

            // ── Step 5: Missing document check ───────────────────────────────
            $hold_note = self::checkMissingDocument($request, $config);

            if ($hold_note !== null) {
                self::writeDecision(
                    $conn,
                    $request_id,
                    'Waiting',
                    self::RULE_MISSING_DOC_HOLD,
                    $hold_note
                );
                return;
            }

            // ── Step 6: Archived item check ──────────────────────────────────
            // Leave as Waiting — do not approve or decline archived items.
            if (isset($request['is_archived']) && (int)$request['is_archived'] === 1) {
                return;
            }

            // ── Step 7: Acquire stock lock (opens transaction) ───────────────
            $quantity = self::acquireStockLock($conn, $equipment_name);

            if ($quantity === null) {
                // Could not acquire lock — leave as Waiting (error handling in 2.13).
                error_log('ArbitrationEngine: acquireStockLock returned null for equipment=' . $equipment_name . ' request_id=' . $request_id);
                return;
            }

            $transaction_open = true;

            // ── Step 8: Stock = 0 check ──────────────────────────────────────
            if ($quantity === 0) {
                self::writeDecision(
                    $conn,
                    $request_id,
                    'Declined',
                    self::RULE_OUT_OF_STOCK,
                    'Out of stock – maximum approved requests reached.'
                );
                $conn->commit();
                $transaction_open = false;
                return;
            }

            // ── Step 9: FIFO + tie-break scoring ─────────────────────────────
            $tie_break_window = (int)($config['tie_break_window_seconds'] ?? 5);

            // Fetch all Waiting requests for the same equipment, ordered FIFO.
            $stmt = $conn->prepare(
                "SELECT r.*, u.role
                   FROM tbl_requests r
                   LEFT JOIN tbl_users u ON r.faculty_id = u.faculty_id
                  WHERE r.equipment_name = ?
                    AND r.status = 'Waiting'
                  ORDER BY r.request_date ASC, r.id ASC"
            );

            if ($stmt === false) {
                $conn->rollback();
                $transaction_open = false;
                return;
            }

            $stmt->bind_param('s', $equipment_name);

            if (!$stmt->execute()) {
                $stmt->close();
                $conn->rollback();
                $transaction_open = false;
                return;
            }

            $result            = $stmt->get_result();
            $competing         = [];

            while ($row = $result->fetch_assoc()) {
                $competing[] = $row;
            }

            $stmt->close();

            // Enrich each competing request with has_overdue and priority score.
            foreach ($competing as &$req) {
                $req['has_overdue']      = self::checkOverdueBlock($conn, (string)$req['faculty_id']);
                $doc_path                = (string)($req['document_path'] ?? '');
                $signatory_level         = ($doc_path !== '') ? self::validateDocument($doc_path) : 0;
                $req['_score']           = self::computePriorityScore($req, $config, $signatory_level);
                $req['_signatory_level'] = $signatory_level;
            }
            unset($req);

            // Determine the earliest request_date among all competing requests.
            $earliest_date = !empty($competing) ? strtotime((string)$competing[0]['request_date']) : 0;

            // Sort competing requests by priority:
            // Within the tie-break window: signatory DESC, role DESC, has_overdue ASC, id ASC
            // Outside the tie-break window: pure FIFO (already ordered by request_date ASC, id ASC)
            usort($competing, function (array $a, array $b) use ($earliest_date, $tie_break_window): int {
                $a_time = strtotime((string)$a['request_date']);
                $b_time = strtotime((string)$b['request_date']);

                $a_in_window = ($a_time - $earliest_date) <= $tie_break_window;
                $b_in_window = ($b_time - $earliest_date) <= $tie_break_window;

                // If both are within the tie-break window, apply full scoring.
                if ($a_in_window && $b_in_window) {
                    $sa = $a['_score'];
                    $sb = $b['_score'];

                    // 1. Signatory DESC (higher is better)
                    if ($sa['signatory'] !== $sb['signatory']) {
                        return $sb['signatory'] - $sa['signatory'];
                    }

                    // 2. Role DESC (higher is better)
                    if ($sa['role'] !== $sb['role']) {
                        return $sb['role'] - $sa['role'];
                    }

                    // 3. has_overdue ASC (0 beats 1 — no overdue is better)
                    $a_overdue = $sa['has_overdue'] ? 1 : 0;
                    $b_overdue = $sb['has_overdue'] ? 1 : 0;

                    if ($a_overdue !== $b_overdue) {
                        return $a_overdue - $b_overdue;
                    }

                    // 4. ID ASC (lower id wins — earliest insertion)
                    return $sa['id'] - $sb['id'];
                }

                // If only one is in the window, the one in the window wins.
                if ($a_in_window && !$b_in_window) {
                    return -1;
                }

                if (!$a_in_window && $b_in_window) {
                    return 1;
                }

                // Both outside the window — pure FIFO: earlier date first, then lower id.
                if ($a_time !== $b_time) {
                    return $a_time - $b_time;
                }

                return (int)$a['id'] - (int)$b['id'];
            });

            // The top-ranked request after sorting is the winner.
            $winner = !empty($competing) ? $competing[0] : null;

            // Determine which rule was applied.
            $applied_rule = self::RULE_1_FIFO;

            if ($winner !== null && count($competing) > 1) {
                $second      = $competing[1];
                $w_time      = strtotime((string)$winner['request_date']);
                $s_time      = strtotime((string)$second['request_date']);
                $in_window   = abs($w_time - $s_time) <= $tie_break_window;

                if ($in_window) {
                    $ws = $winner['_score'];
                    $ss = $second['_score'];

                    if ($ws['signatory'] !== $ss['signatory']) {
                        $applied_rule = self::RULE_2_SIGNATORY;
                    } elseif ($ws['role'] !== $ss['role']) {
                        $applied_rule = self::RULE_3_ROLE;
                    } elseif (($ws['has_overdue'] ? 1 : 0) !== ($ss['has_overdue'] ? 1 : 0)) {
                        $applied_rule = self::RULE_4_RETURN_HISTORY;
                    } else {
                        $applied_rule = self::RULE_5_ID_ORDER;
                    }
                }
            }

            // ── Step 10: Write decision ──────────────────────────────────────
            // Only approve if the current request is the winner.
            $current_is_winner = ($winner !== null && (int)$winner['id'] === $request_id);

            if ($current_is_winner) {
                // Approve this request.
                self::writeDecision(
                    $conn,
                    $request_id,
                    'Approved',
                    $applied_rule,
                    'Request approved via FIFO priority scoring.'
                );

                // Decrement inventory.
                $upd = $conn->prepare(
                    'UPDATE tbl_inventory SET quantity = quantity - 1 WHERE item_name = ?'
                );

                if ($upd === false) {
                    $conn->rollback();
                    $transaction_open = false;
                    return;
                }

                $upd->bind_param('s', $equipment_name);

                if (!$upd->execute()) {
                    $upd->close();
                    $conn->rollback();
                    $transaction_open = false;
                    return;
                }

                $upd->close();

                // Check new quantity — cascade-decline if stock hits 0.
                $qty_stmt = $conn->prepare(
                    'SELECT quantity FROM tbl_inventory WHERE item_name = ?'
                );

                if ($qty_stmt !== false) {
                    $qty_stmt->bind_param('s', $equipment_name);
                    $qty_stmt->execute();
                    $qty_result  = $qty_stmt->get_result();
                    $qty_row     = $qty_result->fetch_assoc();
                    $qty_stmt->close();

                    if ($qty_row !== null && (int)$qty_row['quantity'] === 0) {
                        self::cascadeDecline(
                            $conn,
                            $equipment_name,
                            'Out of stock – maximum approved requests reached.'
                        );
                    }
                }
            }
            // If the current request is not the winner, it stays Waiting
            // (the winner will be processed when its own process() call runs).

            $conn->commit();
            $transaction_open = false;

        } catch (\Throwable $e) {
            if ($transaction_open) {
                $conn->rollback();
            }

            // Requirement 2.5: Leave status as Waiting (do NOT update tbl_requests.status).
            // Log the error to PHP's error log.
            error_log(
                'ArbitrationEngine::process — unexpected error for request_id='
                . $request_id . ': ' . $e->getMessage()
            );

            // Insert an arbitration_error entry into tbl_arbitration_log.
            // Wrapped in its own try/catch so a logging failure doesn't cause further issues.
            try {
                // Fetch borrower info using $conn->query() + real_escape_string()
                // to avoid prepared-statement issues when the connection may be in a bad state.
                $safe_id       = $conn->real_escape_string((string)$request_id);
                $log_result    = $conn->query(
                    "SELECT r.faculty_id, r.equipment_name, u.fullname AS borrower_name
                       FROM tbl_requests r
                       LEFT JOIN tbl_users u ON r.faculty_id = u.faculty_id
                      WHERE r.id = {$safe_id}
                      LIMIT 1"
                );

                $borrower_id   = '';
                $borrower_name = '';
                $equipment     = '';

                if ($log_result !== false) {
                    $log_row = $log_result->fetch_assoc();

                    if ($log_row !== null && $log_row !== false) {
                        $borrower_id   = (string)($log_row['faculty_id']    ?? '');
                        $borrower_name = (string)($log_row['borrower_name'] ?? '');
                        $equipment     = (string)($log_row['equipment_name'] ?? '');
                    }

                    $log_result->free();
                }

                $safe_request_id   = $conn->real_escape_string((string)$request_id);
                $safe_borrower_id  = $conn->real_escape_string($borrower_id);
                $safe_borrower_name = $conn->real_escape_string($borrower_name);
                $safe_equipment    = $conn->real_escape_string($equipment);
                $safe_decision     = 'Error';
                $safe_rule         = $conn->real_escape_string(self::RULE_ERROR);
                $safe_reason       = $conn->real_escape_string(
                    'Arbitration error – please contact the administrator.'
                );

                $conn->query(
                    "INSERT IGNORE INTO tbl_arbitration_log
                       (request_id, borrower_id, borrower_name, equipment_name,
                        decision, rule_applied, reason, override_by, override_reason, created_at)
                     VALUES
                       ('{$safe_request_id}', '{$safe_borrower_id}', '{$safe_borrower_name}',
                        '{$safe_equipment}', '{$safe_decision}', '{$safe_rule}',
                        '{$safe_reason}', NULL, NULL, NOW())"
                );
            } catch (\Throwable $log_e) {
                // Silently swallow — a logging failure must not propagate.
                error_log(
                    'ArbitrationEngine::process — failed to write error log for request_id='
                    . $request_id . ': ' . $log_e->getMessage()
                );
            }
        }
    }

    // ── Private helper methods (stubs) ───────────────────────────────────────

    /**
     * Read all rows from tbl_arbitration_config into a key→value map.
     *
     * @param  mysqli $conn Active DB connection
     * @return array<string, string> Map of config_key → config_value
     */
    private static function loadConfig(mysqli $conn): array
    {
        $config = [];

        $result = $conn->query(
            'SELECT config_key, config_value FROM tbl_arbitration_config'
        );

        if ($result === false) {
            return $config;
        }

        while ($row = $result->fetch_assoc()) {
            $config[$row['config_key']] = $row['config_value'];
        }

        $result->free();

        return $config;
    }

    /**
     * Fetch the full request row joined with tbl_users.role.
     *
     * @param  mysqli   $conn Active DB connection
     * @param  int      $id   PK of the tbl_requests row
     * @return array<string, mixed>|null  Row data, or null if not found
     */
    private static function fetchRequest(mysqli $conn, int $id): ?array
    {
        $stmt = $conn->prepare(
            // tbl_requests.* includes submitted_as (added by the dual-borrowing-mode migration);
            // NULL for legacy rows — used by computePriorityScore() and checkMissingDocument().
            'SELECT tbl_requests.*, tbl_users.role,
                    tbl_inventory.is_archived,
                    tbl_inventory.quantity
             FROM tbl_requests
             LEFT JOIN tbl_users
                    ON tbl_requests.faculty_id = tbl_users.faculty_id
             LEFT JOIN tbl_inventory
                    ON tbl_requests.equipment_name = tbl_inventory.item_name
             WHERE tbl_requests.id = ?'
        );

        if ($stmt === false) {
            return null;
        }

        $stmt->bind_param('i', $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        return $row !== false ? $row : null;
    }

    /**
     * Return true if the borrower has one or more unresolved Overdue records.
     *
     * @param  mysqli $conn       Active DB connection
     * @param  string $faculty_id Borrower's faculty_id
     * @return bool
     */
    private static function checkOverdueBlock(mysqli $conn, string $faculty_id): bool
    {
        $stmt = $conn->prepare(
            'SELECT 1 FROM tbl_requests
              WHERE faculty_id = ? AND status = \'Overdue\'
              LIMIT 1'
        );

        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('s', $faculty_id);

        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $stmt->store_result();
        $has_overdue = $stmt->num_rows > 0;
        $stmt->close();

        return $has_overdue;
    }

    /**
     * Return true if the borrower already has an Approved or Waiting request
     * for the same equipment_name.
     *
     * @param  mysqli $conn           Active DB connection
     * @param  string $faculty_id     Borrower's faculty_id
     * @param  string $equipment_name Name of the requested item
     * @return bool
     */
    private static function checkDuplicateBlock(
        mysqli $conn,
        string $faculty_id,
        string $equipment_name,
        int $request_id
    ): bool {
        $stmt = $conn->prepare(
            "SELECT 1 FROM tbl_requests
                WHERE faculty_id = ?
                    AND equipment_name = ?
                    AND status IN ('Approved', 'Waiting')
                    AND id != ?
                LIMIT 1"
        );

        if ($stmt === false) {
            return false;
        }

        $stmt->bind_param('ssi', $faculty_id, $equipment_name, $request_id);

        if (!$stmt->execute()) {
            $stmt->close();
            return false;
        }

        $stmt->store_result();
        $has_duplicate = $stmt->num_rows > 0;
        $stmt->close();

        return $has_duplicate;
    }

    /**
     * Return a hold-note string if a document is required but absent,
     * or null if no document hold applies.
     *
     * @param  array<string, mixed> $request Full request row (includes borrower role)
     * @param  array<string, string> $config  Loaded arbitration config map
     * @return string|null  Hold note, or null if no hold required
     */
    private static function checkMissingDocument(array $request, array $config): ?string
    {
        // Step 1: Check if the missing-doc block rule is enabled.
        if (($config['rule_missing_doc_block_enabled'] ?? '0') !== '1') {
            return null;
        }

        // Step 2: Determine if a document is present.
        $has_document = isset($request['document_path'])
            && $request['document_path'] !== null
            && $request['document_path'] !== '';

        // Step 3: Document present — no hold needed.
        if ($has_document) {
            return null;
        }

        // Step 4: No document — check if this is an adviser-mode request.
        // Two paths qualify:
        //   (a) submitted_as = 'adviser'  → new explicit mode (Requirements 7.1)
        //   (b) submitted_as = NULL AND role = 'Organization Adviser' → legacy path (Requirement 7.2)
        // Personal-mode submissions (submitted_as = 'personal') do NOT trigger this hold
        // based on allow_org_borrowing alone (Requirement 7.3).
        $submitted_as    = isset($request['submitted_as']) ? (string)$request['submitted_as'] : null;
        $is_adviser_mode = ($submitted_as === 'adviser')
                           || ($submitted_as === null && isset($request['role']) && $request['role'] === 'Organization Adviser');

        if ($is_adviser_mode) {
            return 'A signed request letter is required for organization borrowing.';
        }

        // No hold required.
        return null;
    }

    /**
     * Parse the uploaded file at $document_path and return the Signatory_Level.
     *
     * Signatory levels:
     *   3 — Director signature found
     *   2 — Department Head / Dept. Head signature found
     *   0 — No match, no file, or image file (no OCR)
     *
     * @param  string $document_path Path to the uploaded file (relative to project root)
     * @return int  0, 2, or 3
     */
    private static function validateDocument(string $document_path): int
    {
        // Step 1: Reject empty path immediately.
        if ($document_path === '') {
            return 0;
        }

        // Step 2: Build absolute path (document_path is relative to project root).
        $absolute_path = __DIR__ . '/../../' . $document_path;

        if (!file_exists($absolute_path)) {
            return 0;
        }

        // Step 3: Detect MIME type from actual file bytes.
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $absolute_path);
        finfo_close($finfo);

        // Step 4: Images — no OCR capability, return 0 immediately.
        if (in_array($mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return 0;
        }

        // Step 5: PDF — keyword-match on raw bytes.
        if ($mime_type === 'application/pdf') {
            $contents = file_get_contents($absolute_path);

            if ($contents === false) {
                return 0;
            }

            // Director takes highest priority (Signatory_Level 3).
            if (stripos($contents, 'Director') !== false) {
                return 3;
            }

            // Department Head / Dept. Head → Signatory_Level 2.
            if (stripos($contents, 'Department Head') !== false
                || stripos($contents, 'Dept. Head') !== false
            ) {
                return 2;
            }

            // No matching keyword found.
            return 0;
        }

        // Step 6: Any other file type — unsupported, return 0.
        return 0;
    }

    /**
     * Compute the priority score tuple for a request.
     *
     * @param  array<string, mixed>  $request         Full request row
     * @param  array<string, string> $config           Loaded arbitration config map
     * @param  int                   $signatory_level  Result of validateDocument()
     * @return array{signatory: int, role: int, has_overdue: bool, id: int}
     */
    private static function computePriorityScore(
        array $request,
        array $config,
        int $signatory_level
    ): array {
        // ── Signatory: pass through the validated document level (0, 2, or 3) ──
        $signatory = $signatory_level;

        // ── Role_Priority: map borrower role to a numeric rank ────────────────
        //
        // Primary path: use submitted_as when it is present (non-NULL).
        // Fallback path: use the legacy u.role switch for Legacy_Request rows
        // (submitted_as = NULL) so their behaviour is entirely unchanged.
        $submitted_as = isset($request['submitted_as']) ? (string)$request['submitted_as'] : null;

        if ($submitted_as !== null) {
            // submitted_as-first branch (Requirements 6.1, 6.2, 6.4, 6.6)
            if ($submitted_as === 'adviser') {
                $role = (int)($config['role_priority_adviser'] ?? 3);
            } elseif ($submitted_as === 'personal' || $submitted_as === 'student') {
                $role = (int)($config['role_priority_faculty'] ?? 2);
            } else {
                // Unknown value — treat as personal and log for investigation.
                $role = (int)($config['role_priority_faculty'] ?? 2);
                error_log('ArbitrationEngine: unknown submitted_as value: ' . $submitted_as . ' for request_id=' . $request['id']);
            }
        } else {
            // Legacy fallback: u.role switch (Requirement 6.3 — NULL submitted_as rows
            // must produce the same decision as before this feature shipped).
            $role_name = $request['role'] ?? null;

            switch ($role_name) {
                case 'Director':
                    $role = (int)($config['role_priority_director'] ?? 4);
                    break;
                case 'Organization Adviser':
                    $role = (int)($config['role_priority_adviser'] ?? 3);
                    break;
                case 'Regular Faculty':
                    $role = (int)($config['role_priority_faculty'] ?? 2);
                    break;
                case 'Student Representative':
                    $role = (int)($config['role_priority_student'] ?? 1);
                    break;
                default:
                    $role = 1; // lowest priority for any unknown/null role
                    break;
            }
        }

        // ── has_overdue: use pre-populated flag from the request array ─────────
        // The caller is responsible for setting $request['has_overdue'] when
        // building the competing-requests list (e.g. via checkOverdueBlock()).
        $has_overdue = (bool)($request['has_overdue'] ?? false);

        // ── id: tie-break of last resort — lower id wins ──────────────────────
        $id = (int)$request['id'];

        return [
            'signatory'   => $signatory,
            'role'        => $role,
            'has_overdue' => $has_overdue,
            'id'          => $id,
        ];
    }

    /**
     * Begin a transaction and acquire a SELECT FOR UPDATE lock on the
     * inventory row for $equipment_name.
     *
     * Returns the current quantity on success, or null if the lock could
     * not be acquired or the item does not exist.
     *
     * @param  mysqli $conn           Active DB connection
     * @param  string $equipment_name Name of the item to lock
     * @return int|null  Current quantity, or null on error
     */
    private static function acquireStockLock(mysqli $conn, string $equipment_name): ?int
    {
        $equipment_name = trim($equipment_name);
        // Step 1: Begin the transaction. Caller is responsible for COMMIT/ROLLBACK.
        if (!$conn->begin_transaction()) {
            return null;
        }

        // Step 2: Prepare the SELECT FOR UPDATE statement to lock the inventory row.
        $stmt = $conn->prepare(
            'SELECT quantity FROM tbl_inventory WHERE item_name = ? FOR UPDATE'
        );

        if ($stmt === false) {
            $conn->rollback();
            return null;
        }

        // Step 3: Bind, execute, and fetch the result.
        $stmt->bind_param('s', $equipment_name);

        if (!$stmt->execute()) {
            $stmt->close();
            $conn->rollback();
            return null;
        }

        $result   = $stmt->get_result();
        $row      = $result->fetch_assoc();
        $stmt->close();

        // Step 4: Return null if the item does not exist in tbl_inventory.
        if ($row === null || $row === false) {
            $conn->rollback();
            return null;
        }

        // Step 5: Return the current quantity as an integer.
        // NOTE: Transaction remains open — caller (process()) must COMMIT or ROLLBACK.
        return (int)$row['quantity'];
    }

    /**
     * Write the arbitration decision:
     *   - UPDATE tbl_requests SET status, arbitration_rule, reason
     *   - INSERT into tbl_arbitration_log
     *
     * @param  mysqli  $conn       Active DB connection
     * @param  int     $request_id PK of the tbl_requests row
     * @param  string  $status     'Approved', 'Declined', or 'Waiting'
     * @param  string  $rule       Rule identifier constant (e.g. self::RULE_1_FIFO)
     * @param  string  $reason     Human-readable reason string
     */
    private static function writeDecision(
        mysqli $conn,
        int $request_id,
        string $status,
        string $rule,
        string $reason
    ): void {
        // ── Step 1: UPDATE tbl_requests ──────────────────────────────────────
        $stmt = $conn->prepare(
            'UPDATE tbl_requests SET status = ?, arbitration_rule = ?, reason = ? WHERE id = ?'
        );

        if ($stmt === false) {
            error_log(
                "ArbitrationEngine::writeDecision — prepare UPDATE failed for request_id={$request_id}: "
                . $conn->error
            );
            return;
        }

        $stmt->bind_param('sssi', $status, $rule, $reason, $request_id);

        if (!$stmt->execute()) {
            error_log(
                "ArbitrationEngine::writeDecision — execute UPDATE failed for request_id={$request_id}: "
                . $stmt->error
            );
            $stmt->close();
            return;
        }

        $stmt->close();

        // ── Step 2: Fetch request data needed for the log ────────────────────
        // Join tbl_requests with tbl_users to get borrower_id, borrower_name,
        // and equipment_name. tbl_users.fullname is the name column.
        $stmt = $conn->prepare(
            'SELECT r.faculty_id, r.equipment_name, u.fullname AS borrower_name
               FROM tbl_requests r
               LEFT JOIN tbl_users u ON r.faculty_id = u.faculty_id
              WHERE r.id = ?'
        );

        if ($stmt === false) {
            error_log(
                "ArbitrationEngine::writeDecision — prepare SELECT failed for request_id={$request_id}: "
                . $conn->error
            );
            return;
        }

        $stmt->bind_param('i', $request_id);

        if (!$stmt->execute()) {
            error_log(
                "ArbitrationEngine::writeDecision — execute SELECT failed for request_id={$request_id}: "
                . $stmt->error
            );
            $stmt->close();
            return;
        }

        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        if ($row === null || $row === false) {
            error_log(
                "ArbitrationEngine::writeDecision — request row not found for request_id={$request_id}"
            );
            return;
        }

        $borrower_id   = (string)$row['faculty_id'];
        $borrower_name = (string)($row['borrower_name'] ?? '');
        $equipment     = (string)$row['equipment_name'];

        // ── Step 3: INSERT into tbl_arbitration_log ──────────────────────────
        // Use INSERT IGNORE so a duplicate request_id (re-evaluation) doesn't
        // throw a fatal exception — the status UPDATE above already recorded
        // the new decision on tbl_requests.
        $stmt = $conn->prepare(
            'INSERT INTO tbl_arbitration_log
            (request_id, borrower_id, borrower_name, equipment_name,
                decision, rule_applied, reason, override_by, override_reason, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL, NULL, NOW())
            ON DUPLICATE KEY UPDATE
            decision = VALUES(decision),
            rule_applied = VALUES(rule_applied),
            reason = VALUES(reason),
            created_at = NOW()'
        );

        if ($stmt === false) {
            error_log(
                "ArbitrationEngine::writeDecision — prepare INSERT log failed for request_id={$request_id}: "
                . $conn->error
            );
            return;
        }

        $stmt->bind_param(
            'issssss',
            $request_id,
            $borrower_id,
            $borrower_name,
            $equipment,
            $status,
            $rule,
            $reason
        );

        if (!$stmt->execute()) {
            error_log(
                "ArbitrationEngine::writeDecision — execute INSERT log failed for request_id={$request_id}: "
                . $stmt->error
            );
        }

        $stmt->close();
    }

    /**
     * Decline all remaining Waiting requests for $equipment_name with $reason.
     *
     * Called after an approval causes stock to reach 0.
     *
     * @param  mysqli $conn           Active DB connection
     * @param  string $equipment_name Name of the now-out-of-stock item
     * @param  string $reason         Decline reason string
     */
    private static function cascadeDecline(
        mysqli $conn,
        string $equipment_name,
        string $reason
    ): void {
        // Step 1: Fetch all Waiting request IDs for this equipment.
        // Join tbl_users so writeDecision() can pull borrower info from the log.
        $stmt = $conn->prepare(
            "SELECT tbl_requests.id
               FROM tbl_requests
               LEFT JOIN tbl_users
                      ON tbl_requests.faculty_id = tbl_users.faculty_id
              WHERE tbl_requests.equipment_name = ?
                AND tbl_requests.status = 'Waiting'"
        );

        if ($stmt === false) {
            error_log(
                "ArbitrationEngine::cascadeDecline — prepare failed for equipment='{$equipment_name}': "
                . $conn->error
            );
            return;
        }

        $stmt->bind_param('s', $equipment_name);

        if (!$stmt->execute()) {
            error_log(
                "ArbitrationEngine::cascadeDecline — execute failed for equipment='{$equipment_name}': "
                . $stmt->error
            );
            $stmt->close();
            return;
        }

        $result = $stmt->get_result();
        $ids    = [];

        while ($row = $result->fetch_assoc()) {
            $ids[] = (int)$row['id'];
        }

        $stmt->close();

        // Step 2: Decline each Waiting request individually.
        foreach ($ids as $id) {
            self::writeDecision(
                $conn,
                $id,
                'Declined',
                self::RULE_OUT_OF_STOCK,
                $reason
            );
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // ROOM RESERVATION ARBITRATION
    // Same engine, different entry point. Reuses all private helpers above.
    // No inventory to decrement — conflict is determined by time overlap.
    // ════════════════════════════════════════════════════════════════════════

    /**
     * Evaluate a single room reservation and write the decision.
     *
     * Called synchronously after a new tbl_room_reservations row is inserted
     * (status defaults to 'Approved' — this method may flip it to 'Declined').
     *
     * Decision flow:
     *  1.  Load config
     *  2.  Fetch reservation + room + borrower data
     *  3.  Pre-flight: overdue block (checks tbl_requests for equipment overdue)
     *  4.  Pre-flight: room status check (Maintenance / Not Bookable → Declined)
     *  5.  Document check (Adviser without doc → Declined immediately for rooms,
     *      unlike equipment where it holds as Waiting — rooms have no stock queue)
     *  6.  Time conflict check + FIFO/priority scoring
     *  7.  Write decision + log
     *
     * @param mysqli $conn           Active DB connection
     * @param int    $reservation_id PK of the tbl_room_reservations row
     */
    public static function processRoomReservation(mysqli $conn, int $reservation_id): void
    {
        try {
            // ── Step 1: Load config ──────────────────────────────────────────
            $config = self::loadConfig($conn);

            // ── Step 2: Fetch reservation + room + borrower ──────────────────
            $stmt = $conn->prepare(
                "SELECT rr.*,
                        r.room_name, r.status AS room_status,
                        u.role
                   FROM tbl_room_reservations rr
                   JOIN tbl_rooms r ON r.room_id = rr.room_id
                   LEFT JOIN tbl_users u ON u.faculty_id = rr.faculty_id
                  WHERE rr.id = ?
                  LIMIT 1"
            );
            if ($stmt === false) return;
            $stmt->bind_param('i', $reservation_id);
            $stmt->execute();
            $reservation = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($reservation === null) {
                error_log('ArbitrationEngine::processRoomReservation — not found: id=' . $reservation_id);
                return;
            }

            $faculty_id   = (string)$reservation['faculty_id'];
            $room_id      = (int)$reservation['room_id'];
            $room_name    = (string)$reservation['room_name'];
            $res_date     = (string)$reservation['reservation_date'];
            $start_time   = (string)$reservation['start_time'];
            $end_time     = (string)$reservation['end_time'];

            // ── Step 2b: Past datetime check ────────────────────────────────
            // Reject any reservation whose date+start_time is already in the past
            // (Asia/Manila timezone, matching the project's timezone convention).
            $now_manila   = new \DateTime('now', new \DateTimeZone('Asia/Manila'));
            $req_datetime = \DateTime::createFromFormat(
                'Y-m-d H:i',
                $res_date . ' ' . substr($start_time, 0, 5),
                new \DateTimeZone('Asia/Manila')
            );
            if ($req_datetime === false || $req_datetime <= $now_manila) {
                self::writeRoomDecision(
                    $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                    (string)$reservation['faculty_name'],
                    'Declined', self::RULE_PAST_DATETIME,
                    'This reservation date and time has already passed.'
                );
                return;
            }

            // ── Step 3: Overdue block ────────────────────────────────────────
            // Checks tbl_requests (equipment overdue) — same rule applies to rooms.
            if (($config['rule_overdue_block_enabled'] ?? '0') === '1') {
                if (self::checkOverdueBlock($conn, $faculty_id)) {
                    self::writeRoomDecision(
                        $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                        (string)$reservation['faculty_name'],
                        'Declined', self::RULE_OVERDUE_BLOCK,
                        'You have an overdue equipment item. Please return it before making a room reservation.'
                    );
                    return;
                }
            }

            // ── Step 4: Room status check ────────────────────────────────────
            $room_status = (string)$reservation['room_status'];
            if ($room_status === 'Maintenance') {
                self::writeRoomDecision(
                    $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                    (string)$reservation['faculty_name'],
                    'Declined', self::RULE_ARCHIVED,
                    'This room is currently under maintenance and cannot be reserved.'
                );
                return;
            }
            if ($room_status === 'Not Bookable') {
                self::writeRoomDecision(
                    $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                    (string)$reservation['faculty_name'],
                    'Declined', self::RULE_ARCHIVED,
                    'This room is not available for reservation.'
                );
                return;
            }

            // ── Step 4b: Operating hours check ──────────────────────────────
            if (!self::checkOperatingHours($start_time, $end_time)) {
                self::writeRoomDecision(
                    $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                    (string)$reservation['faculty_name'],
                    'Declined', self::RULE_ARCHIVED,
                    'Reservations must be within operating hours: 7:00 AM to 8:00 PM.'
                );
                return;
            }

            // ── Step 5: Missing document check ───────────────────────────────
            // Rooms have no stock queue — Adviser without document → Declined immediately            // (unlike equipment, which holds as Waiting until the document arrives).
            $hold_note = self::checkMissingDocument($reservation, $config);
            if ($hold_note !== null) {
                self::writeRoomDecision(
                    $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                    (string)$reservation['faculty_name'],
                    'Declined', self::RULE_MISSING_DOC_HOLD,
                    $hold_note
                );
                return;
            }

            // ── Step 6: Time conflict check + FIFO/priority scoring ──────────
            // Fetch all Approved reservations for this room/date that overlap
            // the requested time window (strict inequality — adjacency is allowed).
            $conflict_stmt = $conn->prepare(
                "SELECT rr.*, u.role
                   FROM tbl_room_reservations rr
                   LEFT JOIN tbl_users u ON u.faculty_id = rr.faculty_id
                  WHERE rr.room_id          = ?
                    AND rr.reservation_date = ?
                    AND rr.start_time       < ?
                    AND rr.end_time         > ?
                    AND rr.status           = 'Approved'
                    AND rr.id              != ?
                  ORDER BY rr.request_date ASC, rr.id ASC"
            );
            if ($conflict_stmt === false) return;
            $conflict_stmt->bind_param('isssi', $room_id, $res_date, $end_time, $start_time, $reservation_id);
            $conflict_stmt->execute();
            $conflict_result = $conflict_stmt->get_result();
            $conflicts       = [];
            while ($row = $conflict_result->fetch_assoc()) {
                $conflicts[] = $row;
            }
            $conflict_stmt->close();

            if (empty($conflicts)) {
                // No conflict — approve immediately.
                self::writeRoomDecision(
                    $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                    (string)$reservation['faculty_name'],
                    'Approved', self::RULE_1_FIFO,
                    'Room reservation approved — no time conflict.'
                );
                return;
            }

            // Conflict exists — run FIFO + priority scoring to decide winner.
            $tie_break_window = (int)($config['tie_break_window_seconds'] ?? 5);

            // Build competing set: all Approved conflicts + this new reservation.
            $competing = $conflicts;
            $competing[] = $reservation; // add the new one

            foreach ($competing as &$req) {
                $req['has_overdue']  = self::checkOverdueBlock($conn, (string)$req['faculty_id']);
                $doc_path            = (string)($req['document_path'] ?? '');
                $signatory_level     = $doc_path !== '' ? self::validateDocument($doc_path) : 0;
                $req['_score']       = self::computePriorityScore($req, $config, $signatory_level);
            }
            unset($req);

            $earliest_date = !empty($competing) ? strtotime((string)$competing[0]['request_date']) : 0;

            usort($competing, function (array $a, array $b) use ($earliest_date, $tie_break_window): int {
                $a_time = strtotime((string)$a['request_date']);
                $b_time = strtotime((string)$b['request_date']);
                $a_in   = ($a_time - $earliest_date) <= $tie_break_window;
                $b_in   = ($b_time - $earliest_date) <= $tie_break_window;

                if ($a_in && $b_in) {
                    $sa = $a['_score'];
                    $sb = $b['_score'];
                    if ($sa['signatory'] !== $sb['signatory']) return $sb['signatory'] - $sa['signatory'];
                    if ($sa['role']      !== $sb['role'])      return $sb['role']      - $sa['role'];
                    $ao = $sa['has_overdue'] ? 1 : 0;
                    $bo = $sb['has_overdue'] ? 1 : 0;
                    if ($ao !== $bo) return $ao - $bo;
                    return $sa['id'] - $sb['id'];
                }
                if ($a_in && !$b_in) return -1;
                if (!$a_in && $b_in) return 1;
                if ($a_time !== $b_time) return $a_time - $b_time;
                return (int)$a['id'] - (int)$b['id'];
            });

            $winner = !empty($competing) ? $competing[0] : null;

            // Determine applied rule
            $applied_rule = self::RULE_1_FIFO;
            if ($winner !== null && count($competing) > 1) {
                $second    = $competing[1];
                $w_time    = strtotime((string)$winner['request_date']);
                $s_time    = strtotime((string)$second['request_date']);
                $in_window = abs($w_time - $s_time) <= $tie_break_window;
                if ($in_window) {
                    $ws = $winner['_score'];
                    $ss = $second['_score'];
                    if ($ws['signatory'] !== $ss['signatory'])                             $applied_rule = self::RULE_2_SIGNATORY;
                    elseif ($ws['role'] !== $ss['role'])                                   $applied_rule = self::RULE_3_ROLE;
                    elseif (($ws['has_overdue'] ? 1 : 0) !== ($ss['has_overdue'] ? 1 : 0)) $applied_rule = self::RULE_4_RETURN_HISTORY;
                    else                                                                    $applied_rule = self::RULE_5_ID_ORDER;
                }
            }

            $current_is_winner = ($winner !== null && (int)$winner['id'] === $reservation_id);

            if ($current_is_winner) {
                // This reservation beats all conflicts — decline the losers.
                foreach ($conflicts as $loser) {
                    self::writeRoomDecision(
                        $conn, (int)$loser['id'], $room_id, $room_name,
                        (string)$loser['faculty_id'], (string)$loser['faculty_name'],
                        'Declined', $applied_rule,
                        'A higher-priority reservation was submitted for the same time slot.'
                    );
                }
                self::writeRoomDecision(
                    $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                    (string)$reservation['faculty_name'],
                    'Approved', $applied_rule,
                    'Room reservation approved via priority scoring.'
                );
            } else {
                // A higher-priority reservation already holds this slot.
                self::writeRoomDecision(
                    $conn, $reservation_id, $room_id, $room_name, $faculty_id,
                    (string)$reservation['faculty_name'],
                    'Declined', $applied_rule,
                    'This time slot is already reserved by a higher-priority request.'
                );
            }

        } catch (\Throwable $e) {
            error_log(
                'ArbitrationEngine::processRoomReservation — unexpected error for reservation_id='
                . $reservation_id . ': ' . $e->getMessage()
            );
        }
    }

    /**
     * Return true if both times fall within operating hours (07:00–20:00).
     * Uses string comparison — valid for 'HH:MM' or 'HH:MM:SS' TIME values.
     *
     * @param  string $start_time  'HH:MM' or 'HH:MM:SS'
     * @param  string $end_time    'HH:MM' or 'HH:MM:SS'
     * @return bool
     */
    private static function checkOperatingHours(string $start_time, string $end_time): bool
    {
        // Normalise to 'HH:MM' for reliable string comparison
        $start = substr($start_time, 0, 5);
        $end   = substr($end_time,   0, 5);
        return $start >= '07:00' && $end <= '20:00';
    }

    /**
     * Write a room reservation decision: UPDATE tbl_room_reservations + INSERT log.
     */
    private static function writeRoomDecision(
        mysqli $conn,
        int    $reservation_id,
        int    $room_id,
        string $room_name,
        string $borrower_id,
        string $borrower_name,
        string $decision,
        string $rule,
        string $reason
    ): void {
        $upd = $conn->prepare(
            "UPDATE tbl_room_reservations SET status = ?, reason = ? WHERE id = ?"
        );
        if ($upd !== false) {
            $upd->bind_param('ssi', $decision, $reason, $reservation_id);
            $upd->execute();
            $upd->close();
        }

        $log = $conn->prepare(
            "INSERT INTO tbl_room_arbitration_log
               (reservation_id, room_id, room_name, borrower_id, borrower_name,
                decision, rule_applied, reason)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if ($log !== false) {
            $log->bind_param('iissssss',
                $reservation_id, $room_id, $room_name,
                $borrower_id, $borrower_name,
                $decision, $rule, $reason
            );
            $log->execute();
            $log->close();
        }
    }
}
