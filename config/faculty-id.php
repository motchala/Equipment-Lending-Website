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
 * Build the local part of a PUPSync email from a full name.
 * "Aleli Joy A. Prestado" → "alelijah.prestado" would be wrong.
 * We use only the FIRST token of the first-name portion and the last token
 * (last name). Middle initial and second given name are stripped.
 * "Aleli Joy" + "Prestado" → "alelijah" is wrong; we use the first word only.
 * "Aleli" → local = "aleli.prestado"
 * "Aleli Joy" → local = "aleli.prestado"  (first word of first name only)
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
 * Lookup a faculty member in tbl_faculty_registry by first + last name.
 * Handles:
 *  - Middle initial stripped from registry name
 *  - Compound first names: "Aleli Joy" matches "Aleli" typed as first name
 *
 * Returns an array of matching rows (may be >1 for disambiguation), or [].
 * Each row: ['id', 'faculty_id', 'full_name', 'department', 'is_newly_hired']
 */
function faculty_registry_lookup(mysqli $conn, string $first_name, string $last_name): array
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

    // Normalize the typed input
    // Last name: remove all non-alpha
    $typed_last  = strtolower(trim(preg_replace('/[^a-zA-Z\s]/', '', $last_name) ?? $last_name));
    // First name: first word only (handles "Aleli Joy" → "aleli")
    $fname_tokens = preg_split('/\s+/', trim($first_name));
    $typed_first_primary = strtolower(preg_replace('/[^a-zA-Z]/', '', $fname_tokens[0] ?? '') ?? '');
    // Full first name for secondary match (e.g. "Aleli Joy")
    $typed_first_full = strtolower(preg_replace('/[^a-zA-Z\s]/', '', $first_name) ?? $first_name);

    $matches = [];
    foreach ($rows as $row) {
        // Parse registry full name: "Aaron A. Atienza" or "Aleli Joy A. Prestado"
        $parts = preg_split('/\s+/', trim($row['full_name']));
        if (count($parts) < 2) continue;

        // Last token is the last name
        $reg_last = strtolower(preg_replace('/[^a-zA-Z]/', '', end($parts)) ?? '');

        // Last name must match
        if ($reg_last !== $typed_last) continue;

        // Build the registry first name (everything before the last name,
        // stripping any single-letter-dot middle initial tokens)
        $first_tokens = array_slice($parts, 0, -1);
        $first_tokens_clean = array_filter($first_tokens, function ($t) {
            return !preg_match('/^[A-Za-z]\.$/', $t);
        });
        $reg_first_full = strtolower(implode(' ', array_values($first_tokens_clean)));
        $reg_first_primary = strtolower(preg_replace('/[^a-zA-Z]/', '', $first_tokens_clean[array_key_first($first_tokens_clean)] ?? '') ?? '');

        // Match on first word of first name (primary) OR full first name
        if ($reg_first_primary === $typed_first_primary ||
            $reg_first_full === $typed_first_full ||
            str_starts_with($reg_first_full, $typed_first_full)
        ) {
            $matches[] = $row;
        }
    }

    return $matches;
}
