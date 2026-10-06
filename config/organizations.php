<?php
/**
 * organizations.php - the student organizations an Organization Adviser can be assigned to.
 *
 * - orgs_required():   the organizations the system must always know about, with their category.
 * - orgs_ensure():     idempotent self-heal (same pattern as admin_accounts_ensure_schema): adds the
 *                      `category` column if it is missing, inserts any required organization that
 *                      does not exist yet, and fills in a missing category. It never overwrites a
 *                      category someone already set.
 * - one adviser per organization: orgs_adviser_conflict() / orgs_lock() are used by the create and
 *                      update endpoints. Every organization has the same privileges; only the
 *                      Organization Adviser role matters, never which organization it is.
 */

const ORG_CATEGORY_ACADEMIC     = 'Academic';
const ORG_CATEGORY_NON_ACADEMIC = 'Non-academic';

/** name => category (null = no category). */
function orgs_required(): array
{
    return [
        'IBITS'  => ORG_CATEGORY_ACADEMIC,
        'ACES'   => ORG_CATEGORY_ACADEMIC,
        'HRSS'   => ORG_CATEGORY_ACADEMIC,
        'YES'    => ORG_CATEGORY_ACADEMIC,
        'PIIE'   => ORG_CATEGORY_ACADEMIC,
        'SMS'    => ORG_CATEGORY_ACADEMIC,
        'LENS'   => ORG_CATEGORY_NON_ACADEMIC,
        'AWS'    => ORG_CATEGORY_NON_ACADEMIC,
        'NXTGEN' => ORG_CATEGORY_NON_ACADEMIC,
        'CSC'    => null,
    ];
}

function orgs_has_category(mysqli $conn): bool
{
    try {
        $res = $conn->query("SHOW COLUMNS FROM tbl_organizations LIKE 'category'");
        return $res && $res->num_rows > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function orgs_ensure(mysqli $conn): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    try {
        $has = orgs_has_category($conn);
        if (!$has) {
            try {
                $conn->query('ALTER TABLE tbl_organizations ADD COLUMN category VARCHAR(30) NULL DEFAULT NULL');
            } catch (Throwable $e) {
                error_log('[orgs_ensure] could not add category column: ' . $e->getMessage());
            }
            $has = orgs_has_category($conn);
        }

        foreach (orgs_required() as $name => $category) {
            $st = $conn->prepare($has
                ? 'SELECT id, category FROM tbl_organizations WHERE name = ? LIMIT 1'
                : 'SELECT id FROM tbl_organizations WHERE name = ? LIMIT 1');
            $st->bind_param('s', $name);
            $st->execute();
            $res = $st->get_result();
            $row = $res ? $res->fetch_assoc() : null;
            $st->close();

            if (!$row) {
                if ($has) {
                    $ins = $conn->prepare('INSERT INTO tbl_organizations (name, category) VALUES (?, ?)');
                    $ins->bind_param('ss', $name, $category);
                } else {
                    $ins = $conn->prepare('INSERT INTO tbl_organizations (name) VALUES (?)');
                    $ins->bind_param('s', $name);
                }
                $ins->execute();
                $ins->close();
            } elseif ($has && $category !== null && ($row['category'] === null || $row['category'] === '')) {
                $up = $conn->prepare('UPDATE tbl_organizations SET category = ? WHERE id = ?');
                $oid = (int)$row['id'];
                $up->bind_param('si', $category, $oid);
                $up->execute();
                $up->close();
            }
        }
    } catch (Throwable $e) {
        error_log('[orgs_ensure] ' . $e->getMessage());   // never break the page over this
    }
}

/** ['Academic' => [[id,name],..], 'Non-academic' => [...], '' => [uncategorized...]] (A-Z within each). */
function orgs_load_grouped(mysqli $conn): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    orgs_ensure($conn);
    $groups = [ORG_CATEGORY_ACADEMIC => [], ORG_CATEGORY_NON_ACADEMIC => [], '' => []];
    try {
        $has = orgs_has_category($conn);
        $res = $conn->query($has
            ? 'SELECT id, name, category FROM tbl_organizations ORDER BY name ASC'
            : 'SELECT id, name FROM tbl_organizations ORDER BY name ASC');
        while ($res && ($r = $res->fetch_assoc())) {
            $cat = $has ? (string)($r['category'] ?? '') : '';
            if (!isset($groups[$cat])) {
                $cat = '';   // a category label this code does not know -> shown uncategorised
            }
            $groups[$cat][] = ['id' => (int)$r['id'], 'name' => (string)$r['name']];
        }
    } catch (Throwable $e) {
        error_log('[orgs_load_grouped] ' . $e->getMessage());   // the page then shows "Organizations unavailable"
    }
    return $cache = $groups;
}

/** organization_id => ['faculty_id' => ..., 'name' => ...] for every organization that already has an adviser. */
function orgs_adviser_map(mysqli $conn): array
{
    $map = [];
    try {
        $res = $conn->query("SELECT organization_id, faculty_id, fullname FROM tbl_users
                              WHERE role = 'Organization Adviser' AND organization_id IS NOT NULL
                              ORDER BY fullname ASC");
        while ($res && ($r = $res->fetch_assoc())) {
            $oid = (int)$r['organization_id'];
            if (!isset($map[$oid])) {
                $map[$oid] = ['faculty_id' => (string)$r['faculty_id'], 'name' => (string)$r['fullname']];
            }
        }
    } catch (Throwable $e) {
        error_log('[orgs_adviser_map] ' . $e->getMessage());
    }
    return $map;
}

/**
 * <option> / <optgroup> markup shared by the create form and the edit modal.
 * Categorised organizations come in "Academic" and "Non-academic" groups; uncategorised ones
 * (e.g. CSC) follow as plain options. With $mark_taken, organizations that already have an
 * adviser are disabled and labelled "(taken)".
 */
function orgs_render_options(array $groups, array $adviser_map = [], bool $mark_taken = false): string
{
    $h = static function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $opt = static function (array $o) use ($adviser_map, $mark_taken, $h) {
        $taken = $mark_taken && isset($adviser_map[$o['id']]);
        return '<option value="' . (int)$o['id'] . '" data-name="' . $h($o['name']) . '"'
            . ($taken ? ' disabled' : '') . '>' . $h($o['name'] . ($taken ? ' (taken)' : '')) . '</option>';
    };
    $out = '';
    foreach ([ORG_CATEGORY_ACADEMIC, ORG_CATEGORY_NON_ACADEMIC] as $cat) {
        if (!empty($groups[$cat])) {
            $out .= '<optgroup label="' . $h($cat) . '">';
            foreach ($groups[$cat] as $o) {
                $out .= $opt($o);
            }
            $out .= '</optgroup>';
        }
    }
    foreach ($groups[''] ?? [] as $o) {
        $out .= $opt($o);
    }
    return $out;
}

/**
 * Lock the organization row so two admins cannot give the same organization two advisers at once.
 * Must be called inside a transaction. Returns false when the organization does not exist.
 */
function orgs_lock(mysqli $conn, int $org_id): bool
{
    $st = $conn->prepare('SELECT id FROM tbl_organizations WHERE id = ? FOR UPDATE');
    $st->bind_param('i', $org_id);
    $st->execute();
    $st->store_result();
    $found = $st->num_rows > 0;
    $st->close();
    return $found;
}

/** Another faculty who is already the adviser of this organization, or null. */
function orgs_adviser_conflict(mysqli $conn, int $org_id, string $exclude_faculty_id = ''): ?array
{
    $st = $conn->prepare("SELECT u.faculty_id, u.fullname, o.name AS org_name
                            FROM tbl_users u
                            JOIN tbl_organizations o ON o.id = u.organization_id
                           WHERE u.role = 'Organization Adviser' AND u.organization_id = ? AND u.faculty_id <> ?
                           LIMIT 1");
    $st->bind_param('is', $org_id, $exclude_faculty_id);
    $st->execute();
    $res = $st->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $st->close();
    return $row ?: null;
}

function orgs_adviser_conflict_message(array $conflict): string
{
    return $conflict['org_name'] . ' already has an adviser: ' . $conflict['fullname'] . '.';
}
