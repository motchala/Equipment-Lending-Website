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
