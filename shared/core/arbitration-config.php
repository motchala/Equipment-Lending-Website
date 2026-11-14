<?php

declare(strict_types=1);

/**
 * shared/core/arbitration-config.php
 *
 * One place that knows every Arbitration setting (Settings → Arbitration, Super Admin only):
 * its type, allowed range and default. It reads tbl_arbitration_config, validates what the
 * Super Admin submits, and saves it. The rest of the system asks this class (usually through
 * BookingSchedule's getters) instead of hard-coding the numbers.
 *
 * Values live in tbl_arbitration_config (config_key, config_value TEXT):
 *   bool      '1' / '0'
 *   int       whole number inside [min, max]
 *   time      'HH:00' (booking times are on the hour)
 *   int_list  comma list such as '1,2,3,6', every number from the allowed set
 *   enum      one of the allowed words
 *   date      'YYYY-MM-DD', or '' when not set
 *
 * A missing or invalid row falls back to the default, so a fresh database behaves exactly as
 * the system did before these settings existed.
 */
final class ArbitrationConfig
{
    /** @var array<string, array<string, mixed>> */
    public const SCHEMA = [
        // ── Equipment: borrowing rules ──────────────────────────────────────
        'rule_overdue_block_enabled'     => ['type' => 'bool', 'default' => false],
        'rule_missing_doc_block_enabled' => ['type' => 'bool', 'default' => false],
        'max_items_per_borrower'         => ['type' => 'int', 'default' => 3,  'min' => 1, 'max' => 20],
        'max_borrow_days'                => ['type' => 'int', 'default' => 1,  'min' => 1, 'max' => 14],
        'max_extensions'                 => ['type' => 'int', 'default' => 2,  'min' => 0, 'max' => 5],
        'low_stock_threshold'            => ['type' => 'int', 'default' => 2,  'min' => 0, 'max' => 20],

        // ── Equipment: booking window ───────────────────────────────────────
        'equip_day_start'                => ['type' => 'time', 'default' => '07:00'],
        'equip_day_end'                  => ['type' => 'time', 'default' => '21:00'],
        'equip_max_advance_days'         => ['type' => 'int', 'default' => 14, 'min' => 1, 'max' => 60],
        'equip_immediate_grace_min'      => ['type' => 'int', 'default' => 15, 'min' => 0, 'max' => 60, 'step' => 5],
        'equip_cancel_cutoff_min'        => ['type' => 'int', 'default' => 60, 'min' => 0, 'max' => 240, 'step' => 15],
        'equip_durations'                => ['type' => 'int_list', 'default' => [1, 2, 3, 6], 'allowed' => [1, 2, 3, 4, 5, 6]],

        // ── Rooms: reservation rules (read through RoomRules) ───────────────
        'room_max_active'                => ['type' => 'int', 'default' => 2,  'min' => 1, 'max' => 10],
        'room_max_advance_weeks'         => ['type' => 'int', 'default' => 3,  'min' => 1, 'max' => 8],
        'room_cancel_cutoff_min'         => ['type' => 'int', 'default' => 60, 'min' => 0, 'max' => 240, 'step' => 15],
        'room_same_day_auto'             => ['type' => 'bool', 'default' => true],
        'room_letter_required'           => ['type' => 'bool', 'default' => false],
        'room_expiry_mode'               => ['type' => 'enum', 'default' => 'day', 'allowed' => ['day', 'start', 'never']],

        // ── Semester (shown as a small tag on every dashboard; the dates also anchor the service-survey
        //    reporting windows). Never saved yet? all() falls back to the dates already in the survey settings. ──
        'semester_name'                  => ['type' => 'enum', 'default' => 'none', 'allowed' => ['first', 'second', 'summer', 'none']],
        'semester_start'                 => ['type' => 'date', 'default' => ''],
        'semester_end'                   => ['type' => 'date', 'default' => ''],
    ];

    /** What each semester_name value is called on screen. */
    public const SEMESTER_LABELS = [
        'first'  => '1st Semester',
        'second' => '2nd Semester',
        'summer' => 'Summer',
        'none'   => 'No active semester',
    ];

    /** @var array<string, mixed>|null typed values, per request */
    private static ?array $cache = null;

    // ═════════════════════════════════════════════════════════════════════
    // Reading
    // ═════════════════════════════════════════════════════════════════════

    /** @return array<string, mixed> every key in SCHEMA at its default */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::SCHEMA as $key => $def) {
            $out[$key] = $def['default'];
        }
        return $out;
    }

    /** Raw tbl_arbitration_config rows as key => string. */
    public static function raw(mysqli $conn): array
    {
        $raw = [];
        try {
            $res = $conn->query('SELECT config_key, config_value FROM tbl_arbitration_config');
            while ($res && ($row = $res->fetch_assoc())) {
                $raw[(string)$row['config_key']] = (string)$row['config_value'];
            }
        } catch (\Throwable $e) {
            error_log('ArbitrationConfig::raw — ' . $e->getMessage());
        }
        return $raw;
    }

    /**
     * Typed values for every SCHEMA key (defaults filled in). Cached for the request;
     * pass $fresh = true right after saving.
     *
     * @return array<string, mixed>
     */
    public static function all(mysqli $conn, bool $fresh = false): array
    {
        if (self::$cache !== null && !$fresh) {
            return self::$cache;
        }
        $raw = self::raw($conn);
        $out = [];
        foreach (self::SCHEMA as $key => $def) {
            $out[$key] = array_key_exists($key, $raw)
                ? self::coerce($def, $raw[$key])
                : $def['default'];
        }
        // Keep the combination sane even if a row was edited by hand.
        $out = self::repairHours($out);
        $out = self::legacySemester($conn, $raw, $out);
        return self::$cache = $out;
    }

    /** @return mixed */
    public static function get(mysqli $conn, string $key)
    {
        $all = self::all($conn);
        return $all[$key] ?? (self::SCHEMA[$key]['default'] ?? null);
    }

    public static function forget(): void
    {
        self::$cache = null;
    }

    // ═════════════════════════════════════════════════════════════════════
    // Validation (what the Super Admin submits)
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Checks submitted values. Only keys in SCHEMA are accepted; anything else is ignored.
     *
     * @param  array<string, mixed> $input key => value (bool, int, 'HH:MM' or int[])
     * @return array{clean: array<string, mixed>, errors: array<string, string>}
     */
    public static function validate(array $input): array
    {
        $clean  = [];
        $errors = [];

        foreach ($input as $key => $value) {
            $key = (string)$key;
            if (!isset(self::SCHEMA[$key])) {
                continue;
            }
            $def = self::SCHEMA[$key];

            switch ($def['type']) {
                case 'bool':
                    if (is_bool($value)) {
                        $clean[$key] = $value;
                    } elseif (in_array($value, [0, 1, '0', '1'], true)) {
                        $clean[$key] = (string)$value === '1';
                    } else {
                        $errors[$key] = 'Invalid on/off value.';
                    }
                    break;

                case 'int':
                    if (!is_numeric($value) || (string)(int)$value !== (string)(0 + $value)) {
                        $errors[$key] = 'Enter a whole number.';
                        break;
                    }
                    $n = (int)$value;
                    if ($n < $def['min'] || $n > $def['max']) {
                        $errors[$key] = 'Must be between ' . $def['min'] . ' and ' . $def['max'] . '.';
                        break;
                    }
                    if (isset($def['step']) && $n % (int)$def['step'] !== 0) {
                        $errors[$key] = 'Must be a multiple of ' . $def['step'] . '.';
                        break;
                    }
                    $clean[$key] = $n;
                    break;

                case 'time':
                    if (!is_string($value) || !preg_match('/^([01]\d|2[0-3]):00$/', $value)) {
                        $errors[$key] = 'Choose a time on the hour.';
                        break;
                    }
                    $clean[$key] = $value;
                    break;

                case 'enum':
                    if (!is_string($value) || !in_array($value, $def['allowed'], true)) {
                        $errors[$key] = 'Choose one of the listed options.';
                        break;
                    }
                    $clean[$key] = $value;
                    break;

                case 'date':
                    if (!is_string($value)) {
                        $errors[$key] = 'Choose a valid date.';
                        break;
                    }
                    $value = trim($value);
                    if ($value !== '' && !self::isDate($value)) {
                        $errors[$key] = 'Choose a valid date.';
                        break;
                    }
                    $clean[$key] = $value;   // '' = not set
                    break;

                case 'int_list':
                    if (!is_array($value)) {
                        $errors[$key] = 'Invalid list.';
                        break;
                    }
                    $nums = [];
                    $bad  = false;
                    foreach ($value as $v) {
                        if (!is_numeric($v) || !in_array((int)$v, $def['allowed'], true)) {
                            $bad = true;
                            break;
                        }
                        $nums[(int)$v] = true;
                    }
                    if ($bad) {
                        $errors[$key] = 'Contains a value that is not allowed.';
                        break;
                    }
                    $nums = array_keys($nums);
                    sort($nums);
                    if (!$nums) {
                        $errors[$key] = 'Choose at least one.';
                        break;
                    }
                    $clean[$key] = $nums;
                    break;
            }
        }

        // Cross-field rules use the submitted value, else the saved one is checked by the caller.
        return ['clean' => $clean, 'errors' => $errors];
    }

    /**
     * Cross-field checks on the full set that would be stored after saving.
     *
     * @param  array<string, mixed> $merged typed values after applying the submission
     * @return array<string, string> key => message
     */
    public static function crossCheck(array $merged): array
    {
        $errors = [];
        $start = self::hour((string)$merged['equip_day_start']);
        $end   = self::hour((string)$merged['equip_day_end']);

        if ($end - $start < 1) {
            $errors['equip_day_end'] = 'The latest return must be at least 1 hour after the earliest pickup.';
        } else {
            $shortest = min($merged['equip_durations']);
            if ($end - $start < $shortest) {
                $errors['equip_durations'] = 'The shortest booking length does not fit inside the booking hours.';
            }
        }

        // Semester dates: both or neither, and in order.
        $semStart = (string)($merged['semester_start'] ?? '');
        $semEnd   = (string)($merged['semester_end'] ?? '');
        if (($semStart === '') !== ($semEnd === '')) {
            $errors['semester_end'] = 'Enter both semester dates, or leave both blank.';
        } elseif ($semStart !== '' && $semStart > $semEnd) {
            $errors['semester_end'] = 'The semester must end on or after the day it starts.';
        }
        return $errors;
    }

    // ═════════════════════════════════════════════════════════════════════
    // Saving
    // ═════════════════════════════════════════════════════════════════════

    /**
     * Writes validated values. Returns the keys whose stored value actually changed.
     *
     * @param  array<string, mixed> $clean output of validate()['clean']
     * @return list<string>
     */
    public static function save(mysqli $conn, array $clean): array
    {
        $changed = [];
        $before  = self::raw($conn);

        $conn->begin_transaction();
        try {
            foreach ($clean as $key => $value) {
                $store = self::serialize((string)$key, $value);
                if (($before[$key] ?? null) === $store) {
                    continue;
                }
                if (array_key_exists($key, $before)) {
                    $st = $conn->prepare('UPDATE tbl_arbitration_config SET config_value = ? WHERE config_key = ?');
                } else {
                    $st = $conn->prepare('INSERT INTO tbl_arbitration_config (config_value, config_key) VALUES (?, ?)');
                }
                if (!$st) {
                    throw new \RuntimeException('prepare failed: ' . $conn->error);
                }
                $st->bind_param('ss', $store, $key);
                if (!$st->execute()) {
                    $err = $st->error;
                    $st->close();
                    throw new \RuntimeException('execute failed: ' . $err);
                }
                $st->close();
                $changed[] = (string)$key;
            }
            $conn->commit();
        } catch (\Throwable $e) {
            $conn->rollback();
            throw $e;
        }

        self::forget();
        return $changed;
    }

    // ═════════════════════════════════════════════════════════════════════
    // Helpers
    // ═════════════════════════════════════════════════════════════════════

    /** @param mixed $value */
    private static function serialize(string $key, $value): string
    {
        $type = self::SCHEMA[$key]['type'];
        if ($type === 'bool') {
            return $value ? '1' : '0';
        }
        if ($type === 'int_list') {
            return implode(',', array_map('intval', (array)$value));
        }
        return (string)$value;
    }

    /**
     * @param  array<string, mixed> $def
     * @return mixed
     */
    private static function coerce(array $def, string $raw)
    {
        switch ($def['type']) {
            case 'bool':
                return $raw === '1';
            case 'int':
                if (!is_numeric($raw)) {
                    return $def['default'];
                }
                return max((int)$def['min'], min((int)$def['max'], (int)$raw));
            case 'time':
                return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $raw) ? substr($raw, 0, 2) . ':00' : $def['default'];
            case 'enum':
                return in_array($raw, $def['allowed'], true) ? $raw : $def['default'];
            case 'date':
                return self::isDate($raw) ? $raw : $def['default'];
            case 'int_list':
                $nums = [];
                foreach (explode(',', $raw) as $p) {
                    $p = trim($p);
                    if ($p !== '' && ctype_digit($p) && in_array((int)$p, $def['allowed'], true)) {
                        $nums[(int)$p] = true;
                    }
                }
                $nums = array_keys($nums);
                sort($nums);
                return $nums ?: $def['default'];
        }
        return $def['default'];
    }

    private static function hour(string $hhmm): int
    {
        return (int)substr($hhmm, 0, 2);
    }

    /** True for a real calendar date written as YYYY-MM-DD. */
    private static function isDate(string $value): bool
    {
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $e = \DateTimeImmutable::getLastErrors();
        return $d !== false
            && $d->format('Y-m-d') === $value
            && ($e === false || ($e['warning_count'] === 0 && $e['error_count'] === 0));
    }

    /** Semester name for a start/end window, going by its middle month: Aug-Dec 1st · Jan-May 2nd · Jun-Jul Summer. */
    private static function semesterFromWindow(string $start, string $end): string
    {
        $mid   = (int)((strtotime($start . ' 00:00:00') + strtotime($end . ' 00:00:00')) / 2);
        $month = (int)date('n', $mid);
        if ($month >= 8) {
            return 'first';
        }
        return $month >= 6 ? 'summer' : 'second';
    }

    /**
     * Until the Super Admin saves the Semester card here, the dates already set in the old survey settings
     * (tbl_service_survey_config) keep working, and the semester name is worked out from them. After the first
     * save here the rows exist and this does nothing.
     */
    private static function legacySemester(mysqli $conn, array $raw, array $out): array
    {
        if (array_key_exists('semester_start', $raw) || array_key_exists('semester_end', $raw) || array_key_exists('semester_name', $raw)) {
            return $out;
        }
        try {
            $res = $conn->query('SELECT semester_start, semester_end FROM tbl_service_survey_config WHERE id = 1 LIMIT 1');
            $row = $res ? $res->fetch_assoc() : null;
            if ($res) {
                $res->free();
            }
        } catch (\Throwable $e) {
            return $out;   // table not created yet
        }
        $start = (string)($row['semester_start'] ?? '');
        $end   = (string)($row['semester_end'] ?? '');
        if (!self::isDate($start) || !self::isDate($end) || $start > $end) {
            return $out;
        }
        $out['semester_start'] = $start;
        $out['semester_end']   = $end;
        $out['semester_name']  = self::semesterFromWindow($start, $end);
        return $out;
    }

    /** If hand-edited rows leave no usable window, fall back to the default hours. */
    private static function repairHours(array $v): array
    {
        $start = self::hour((string)$v['equip_day_start']);
        $end   = self::hour((string)$v['equip_day_end']);
        if ($end - $start < max(1, min($v['equip_durations']))) {
            $v['equip_day_start'] = self::SCHEMA['equip_day_start']['default'];
            $v['equip_day_end']   = self::SCHEMA['equip_day_end']['default'];
        }
        return $v;
    }
}
