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
 *  5.  Document / high-value check (missing doc → hold as Waiting)
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
            'SELECT tbl_requests.*, tbl_users.role,
                    tbl_inventory.is_high_value,
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

        // Step 4: No document — evaluate which hold note applies.
        $is_high_value        = isset($request['is_high_value']) && $request['is_high_value'] == 1;
        $is_organization_req  = isset($request['role']) && $request['role'] === 'Adviser';

        // Requirement 8.3: High_Value + Organization → use organization message.
        // Requirement 8.2: Organization only → organization message.
        if ($is_organization_req) {
            return 'A signed request letter is required for organization borrowing.';
        }

        // Requirement 8.1: High_Value only (not Organization) → director message.
        if ($is_high_value) {
            return 'A signed request letter from the Director is required for this equipment.';
        }

        // Neither condition applies — no hold.
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

        // Step 2: Build absolute path (file lives in includes/, project root is one level up).
        $absolute_path = __DIR__ . '/../' . $document_path;

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
        $role_name = $request['role'] ?? null;

        switch ($role_name) {
            case 'Director':
                $role = (int)($config['role_priority_director'] ?? 4);
                break;
            case 'Adviser':
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
}