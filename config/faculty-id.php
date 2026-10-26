<?php

/**
 * faculty-id.php - rules for the "Faculty ID" a faculty member sets for themselves.
 *
 * Why a placeholder exists
 * ------------------------
 * tbl_users.faculty_id is the table's PRIMARY KEY and every other table
 * (requests, reservations, codes, ...) stores it as plain text, so a row can
 * never hold an empty or NULL id. When an admin creates an account the id is
 * therefore stored as a unique internal placeholder ("NOTSET-" + random hex).
 * The UI treats a placeholder as "no Faculty ID yet" and shows it as blank /
 * "Not set yet". When the faculty member sets their real id
 * (equipment-booking/api/update-profile.php -> set_faculty_id) the placeholder
 * is replaced everywhere it appears, and the real id is permanent from then on.
 */

const FACULTY_ID_UNSET_PREFIX = 'NOTSET-';

/** True when the account has no real Faculty ID yet (placeholder or empty). */
function faculty_id_is_unset(?string $id): bool
{
    $id = trim((string)$id);
    return $id === '' || strncmp($id, FACULTY_ID_UNSET_PREFIX, strlen(FACULTY_ID_UNSET_PREFIX)) === 0;
}

/** A unique internal placeholder (19 chars, fits every faculty_id column). */
function faculty_id_make_placeholder(): string
{
    return FACULTY_ID_UNSET_PREFIX . strtoupper(bin2hex(random_bytes(6)));
}

/** What to show people: the real id, or $fallback while it is still unset. */
function faculty_id_display(?string $id, string $fallback = ''): string
{
    return faculty_id_is_unset($id) ? $fallback : (string)$id;
}

/**
 * Clean up and validate an id typed by a faculty member.
 * Returns the normalized (upper-case) id, or null when it is not acceptable:
 * 5-30 characters, letters / digits in hyphen-separated groups, at least one digit.
 */
function faculty_id_normalize(string $raw): ?string
{
    $id = strtoupper(trim($raw));
    $len = strlen($id);
    if ($len < 5 || $len > 30) {
        return null;
    }
    if (!preg_match('/^[A-Z0-9]+(?:-[A-Z0-9]+)*$/', $id)) {
        return null;
    }
    if (!preg_match('/\d/', $id)) {
        return null;
    }
    if (strncmp($id, FACULTY_ID_UNSET_PREFIX, strlen(FACULTY_ID_UNSET_PREFIX)) === 0) {
        return null;
    }
    return $id;
}

/** Faculty accounts must use a PUPSync address. */
const FACULTY_EMAIL_DOMAIN = 'pupsync.edu';

/**
 * Clean up and validate a faculty PUPSync email: trimmed and lower-cased.
 * Returns null unless it is a valid address whose domain is exactly @pupsync.edu
 * (so "a@pupsync.edu.evil.com" and "a@mail.pupsync.edu" are both refused).
 */
function faculty_email_normalize(string $raw): ?string
{
    $email = strtolower(trim($raw));
    if ($email === '' || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return null;
    }
    $suffix = '@' . FACULTY_EMAIL_DOMAIN;
    if (substr($email, -strlen($suffix)) !== $suffix) {
        return null;
    }
    return $email;
}

/**
 * Normalize a name from the registry for matching purposes.
 * Strips middle initials (single letter followed by a period), collapses
 * whitespace, lowercases. "Aaron A. Atienza" → "aaron atienza".
 */
function faculty_name_normalize(string $name): string
{
    // Remove middle initial: any (single-letter)(.) surrounded by spaces
    $name = preg_replace('/\s+[A-Za-z]\.\s+/', ' ', $name);
    // Collapse whitespace and lowercase
    return strtolower(trim(preg_replace('/\s+/', ' ', $name) ?? $name));
}

/**
 * Capitalise a person's name the way it should be stored: the first letter of every
 * space- (or hyphen-) separated word is upper-case, so "juan  dela cruz" -> "Juan Dela Cruz"
 * and a two-part first name such as "aleli joy" -> "Aleli Joy". Extra spaces are collapsed;
 * the other letters are left exactly as typed (so "McDonald" stays "McDonald").
 */
function faculty_name_capitalize(string $name): string
{
    $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
    if ($name === '') return '';
    return preg_replace_callback(
        '/(^|[\s\-])(\p{L})/u',
        static function (array $m): string {
            return $m[1] . (function_exists('mb_strtoupper') ? mb_strtoupper($m[2], 'UTF-8') : strtoupper($m[2]));
        },
        $name
    ) ?? $name;
}

/**
 * Build a PUPSync email from a first name and a last name (the whole surname).
 *   - first part  = the FIRST word of the first name only ("Aleli Joy" -> "aleli")
 *   - second part = the WHOLE surname, spaces/punctuation dropped
 *       "Prestado"   -> aleli.prestado@pupsync.edu
 *       "Dela Cruz"  -> edmundo.delacruz@pupsync.edu   (both words are the surname)
 * The middle initial is never part of it. Pass the surname as given by
 * faculty_registry_name_parts(): everything after the middle initial, or the last word
 * when the registry name has no initial.
 */
function faculty_email_generate(string $first_name, string $last_name): string
{
    // First word of the first name only (handles "Aleli Joy" → "aleli")
    $first_tokens = preg_split('/\s+/', trim($first_name));
    $first = strtolower(preg_replace('/[^a-z]/i', '', $first_tokens[0] ?? '') ?? '');
    $last  = strtolower(preg_replace('/[^a-z]/i', '', trim($last_name)) ?? '');
    return $first . '.' . $last . '@' . FACULTY_EMAIL_DOMAIN;
}

/**
 * Resolve email conflicts: if `$base_email` is already taken in tbl_users,
 * try appending the middle initial, then sequential numbers.
 * Returns the first available email address.
 *
 * @param mysqli $conn
 * @param string $base_email  e.g. "juan.delacruz@pupsync.edu"
 * @param string $middle_initial  e.g. "C" (single letter, no period) or ""
 * @param string|null $exclude_faculty_id  skip this row when checking (for edits)
 */
function faculty_email_resolve_conflict(
    mysqli $conn,
    string $base_email,
    string $middle_initial,
    ?string $exclude_faculty_id = null
): string {
    $check = function (string $email) use ($conn, $exclude_faculty_id): bool {
        $sql = "SELECT 1 FROM tbl_users WHERE email = ?";
        if ($exclude_faculty_id !== null) {
            $sql .= " AND faculty_id != ?";
        }
        $sql .= " LIMIT 1";
        $stmt = $conn->prepare($sql);
        if (!$stmt) return true; // assume taken on error
        if ($exclude_faculty_id !== null) {
            $stmt->bind_param('ss', $email, $exclude_faculty_id);
        } else {
            $stmt->bind_param('s', $email);
        }
        $stmt->execute();
        $stmt->store_result();
        $taken = $stmt->num_rows > 0;
        $stmt->close();
        return $taken;
    };

    if (!$check($base_email)) return $base_email;

    // Try middle initial: "juan.c.delacruz@pupsync.edu"
    if ($middle_initial !== '') {
        $at = strpos($base_email, '@');
        $local = substr($base_email, 0, $at);
        $domain = substr($base_email, $at);
        $dot_pos = strpos($local, '.');
        if ($dot_pos !== false) {
            $with_initial = substr($local, 0, $dot_pos + 1)
                . strtolower($middle_initial) . '.'
                . substr($local, $dot_pos + 1)
                . $domain;
            if (!$check($with_initial)) return $with_initial;
        }
    }

    // Sequential number fallback: "juan.delacruz2@pupsync.edu"
    $at = strpos($base_email, '@');
    $local = substr($base_email, 0, $at);
    $domain = substr($base_email, $at);
    for ($n = 2; $n <= 99; $n++) {
        $candidate = $local . $n . $domain;
        if (!$check($candidate)) return $candidate;
    }

    // Extremely unlikely fallback
    return $local . bin2hex(random_bytes(3)) . $domain;
}

/**
 * Split a registry name into given name(s), middle initial and surname.
 *
 *  - WITH a middle initial ("Edmundo B. Dela Cruz"): everything AFTER the initial is the
 *    surname ("Dela Cruz"), everything before it is the given name(s) ("Edmundo"). This is how
 *    a surname written as several words is recognised.
 *  - WITHOUT one ("Maria Santos", "Maria Dela Cruz"): the literal LAST word is the surname,
 *    so "Maria Dela Cruz" reads as given name "Maria Dela" + surname "Cruz". The admin can
 *    still correct the generated email for such names.
 *
 * Returns ['first' => 'Edmundo', 'middle' => 'B' (or ''), 'last' => 'Dela Cruz', 'has_middle' => bool].
 */
function faculty_registry_name_parts(string $full_name): array
{
    $tokens = preg_split('/\s+/', trim($full_name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $n = count($tokens);
    $is_initial = static function (string $t): bool {
        return (bool)preg_match('/^[A-Za-z]\.$/', $t);
    };
    $clean_first = static function (array $toks) use ($is_initial): string {
        // extra initials ("Maria A. B. Santos") are not part of the given name
        return implode(' ', array_values(array_filter($toks, static function ($t) use ($is_initial) {
            return !$is_initial($t);
        })));
    };

    if ($n < 2) {
        return ['first' => trim($full_name), 'middle' => '', 'last' => '', 'has_middle' => false];
    }

    // The LAST single-letter-plus-dot token that has a name before AND after it is the middle initial.
    $k = -1;
    for ($i = $n - 2; $i >= 1; $i--) {
        if ($is_initial($tokens[$i])) {
            $k = $i;
            break;
        }
    }
    if ($k >= 1) {
        return [
            'first'      => $clean_first(array_slice($tokens, 0, $k)),
            'middle'     => strtoupper($tokens[$k][0]),
            'last'       => implode(' ', array_slice($tokens, $k + 1)),
            'has_middle' => true,
        ];
    }

    return [
        'first'      => $clean_first(array_slice($tokens, 0, $n - 1)),
        'middle'     => '',
        'last'       => $tokens[$n - 1],
        'has_middle' => false,
    ];
}

/** Letters only, lower-case: "Dela Cruz" / "dela-cruz" / "DelaCruz" all become "delacruz". */
function faculty_name_letters(string $s): string
{
    return strtolower(preg_replace('/[^a-zA-Z]/', '', $s) ?? '');
}

/** Does a typed first name match a registry given name (first word, whole name, or the start of it)? */
function faculty_first_name_matches(string $typed_first, string $reg_first): bool
{
    $typed_tokens = preg_split('/\s+/', trim($typed_first), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $reg_tokens   = preg_split('/\s+/', trim($reg_first), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $typed_primary = faculty_name_letters($typed_tokens[0] ?? '');
    $reg_primary   = faculty_name_letters($reg_tokens[0] ?? '');
    $typed_full = strtolower(trim(preg_replace('/[^a-zA-Z\s]/', '', $typed_first) ?? $typed_first));
    $reg_full   = strtolower(trim(preg_replace('/[^a-zA-Z\s]/', '', $reg_first) ?? $reg_first));
    if ($typed_primary === '' || $reg_primary === '') return false;
    return $reg_primary === $typed_primary
        || $reg_full === $typed_full
        || ($typed_full !== '' && str_starts_with($reg_full, $typed_full));
}

/**
 * Lookup a faculty member in tbl_faculty_registry by first + last name.
 *
 * The surname comes from faculty_registry_name_parts(): everything after the middle initial
 * ("Edmundo B. Dela Cruz" -> "Dela Cruz"), or the literal last word when there is no initial.
 * The typed last name must equal that whole surname (spaces, case and punctuation are ignored,
 * so "Dela Cruz", "dela  cruz" and "DelaCruz" all match; "Cruz" alone does not).
 * Compound first names work: "Aleli Joy" and "Aleli" both match "Aleli Joy A. Prestado".
 *
 * Returns an array of matching rows (may be >1 for disambiguation), or [].
 * Each row: ['id', 'faculty_id', 'full_name', 'department', 'is_newly_hired']
 */
function faculty_registry_lookup(mysqli $conn, string $first_name, string $last_name): array
{
    $typed_last = faculty_name_letters($last_name);
    if ($typed_last === '') return [];

    $matches = [];
    foreach (faculty_registry_rows($conn) as $row) {
        $parts = faculty_registry_name_parts((string)$row['full_name']);
        if ($parts['last'] === '') continue;
        if (faculty_name_letters($parts['last']) !== $typed_last) continue;
        if (!faculty_first_name_matches($first_name, $parts['first'])) continue;
        $matches[] = $row;
    }
    return $matches;
}

/** All registry rows, ordered by name. */
function faculty_registry_rows(mysqli $conn): array
{
    $stmt = $conn->prepare(
        "SELECT id, faculty_id, full_name, department, is_newly_hired
           FROM tbl_faculty_registry
          ORDER BY full_name ASC"
    );
    if (!$stmt) return [];
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

/**
 * "Did you mean...?" help for a lookup that found nothing. Returns up to 3 of
 * ['full_name', 'first', 'last']: the registry entry plus how to type it so it matches.
 *   - typed "Edmundo" + "Cruz" for "Edmundo B. Dela Cruz"  -> last name should be "Dela Cruz"
 *   - typed "Maria" + "Dela Cruz" for "Maria Dela Cruz" (no initial) -> "Maria Dela" + "Cruz"
 */
function faculty_registry_hints(mysqli $conn, string $first_name, string $last_name): array
{
    $typed_last = faculty_name_letters($last_name);
    $typed_all  = faculty_name_letters($first_name . $last_name);
    if ($typed_last === '') return [];

    $hints = [];
    foreach (faculty_registry_rows($conn) as $row) {
        $parts = faculty_registry_name_parts((string)$row['full_name']);
        if ($parts['last'] === '' || $parts['first'] === '') continue;
        // an entry the lookup itself would match is not a "did you mean"
        if (
            faculty_name_letters($parts['last']) === $typed_last
            && faculty_first_name_matches($first_name, $parts['first'])
        ) continue;

        $last_words = preg_split('/\s+/', trim($parts['last']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $same_name_ignoring_spaces = faculty_name_letters($parts['first'] . $parts['last']) === $typed_all;
        $typed_is_end_of_surname = count($last_words) > 1
            && faculty_name_letters((string)end($last_words)) === $typed_last
            && faculty_first_name_matches($first_name, $parts['first']);

        if ($same_name_ignoring_spaces || $typed_is_end_of_surname) {
            $hints[] = [
                'full_name' => (string)$row['full_name'],
                'first'     => $parts['first'],
                'last'      => $parts['last'],
            ];
            if (count($hints) >= 3) break;
        }
    }
    return $hints;
}
