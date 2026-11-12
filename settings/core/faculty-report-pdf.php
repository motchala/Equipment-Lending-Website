<?php

declare(strict_types=1);

/**
 * settings/core/faculty-report-pdf.php
 *
 * LAYOUT half of the faculty "Export Data" report: turns the array built by fr_build_report()
 * (settings/core/faculty-report.php) into a PDF (US Letter, landscape) using the bundled FPDF
 * (vendor/fpdf). Nothing in here touches the database or the session.
 *
 *   fr_render_pdf(array $report): string     the PDF file as a string
 *
 * FPDF's built-in fonts are single-byte (Windows-1252), so every string is converted once, in
 * FacultyReportPdf::t(). That covers English and Filipino names (ñ, é, ...); a character outside it
 * is transliterated or dropped instead of breaking the file.
 */

require_once __DIR__ . '/../../vendor/fpdf/fpdf.php';
require_once __DIR__ . '/faculty-report.php';      // fr_time(), fr_hours(), FR_ROW_LIMIT

class FacultyReportPdf extends FPDF
{
    /** Page geometry (mm). US Letter landscape is 279.4 x 215.9. */
    private const MARGIN_X  = 10.0;
    private const TOP       = 22.0;
    private const BOTTOM    = 16.0;

    /** Palette. */
    private const MAROON    = [128, 0, 0];
    private const GOLD      = [201, 162, 39];
    private const INK       = [33, 33, 33];
    private const MUTED     = [110, 110, 110];
    private const RULE      = [220, 220, 220];
    private const ZEBRA     = [250, 247, 247];
    private const STUDENT   = [255, 246, 214];

    /** Soft block colours for the timetable (fill, border). */
    private const BLOCKS = [
        [[253, 232, 232], [192, 80, 80]],
        [[226, 239, 253], [70, 120, 190]],
        [[225, 245, 230], [60, 150, 90]],
        [[255, 240, 214], [200, 140, 40]],
        [[240, 230, 252], [130, 90, 190]],
        [[224, 245, 246], [50, 150, 160]],
        [[252, 232, 244], [190, 80, 140]],
        [[238, 240, 214], [140, 150, 50]],
    ];

    private string $who = '';
    private string $stamp = '';

    public function __construct()
    {
        parent::__construct('L', 'mm', 'Letter');
        $this->SetMargins(self::MARGIN_X, self::TOP, self::MARGIN_X);
        $this->SetAutoPageBreak(false);          // page breaks are decided by ensure(), so rows are never cut in half
        $this->AliasNbPages('{nb}');
        $this->SetCompression(true);
    }

    public function setMeta(string $who, string $stamp): void
    {
        $this->who   = $who;
        $this->stamp = $stamp;
    }

    /* ── text ─────────────────────────────────────────────────────────── */

    /** UTF-8 -> the single-byte encoding FPDF's core fonts use. */
    public function t(string $s): string
    {
        $s = str_replace(["\u{2011}", "\u{00A0}", "\u{202F}"], ['-', ' ', ' '], $s);
        if ($s === '' || !preg_match('/[\x80-\xFF]/', $s)) {
            return $s;
        }
        if (function_exists('iconv')) {
            $r = @iconv('UTF-8', 'CP1252//TRANSLIT//IGNORE', $s);
            if ($r !== false) {
                return $r;
            }
        }
        if (function_exists('mb_convert_encoding')) {
            return (string)@mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
        }
        return (string)preg_replace('/[\x80-\xFF]+/', '?', $s);
    }

    /** Word-wrap UTF-8 text to a width with the CURRENT font. Returns already-encoded lines. */
    private function wrap(string $txt, float $w): array
    {
        $txt = $this->t($txt);
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $txt) ?: [''] as $para) {
            if ($para === '') {
                $out[] = '';
                continue;
            }
            $line = '';
            foreach (explode(' ', $para) as $word) {
                $try = $line === '' ? $word : $line . ' ' . $word;
                if ($this->GetStringWidth($try) <= $w) {
                    $line = $try;
                    continue;
                }
                if ($line !== '') {
                    $out[] = $line;
                    $line  = '';
                }
                while (strlen($word) > 1 && $this->GetStringWidth($word) > $w) {   // a word wider than the cell
                    $cut = strlen($word) - 1;
                    while ($cut > 1 && $this->GetStringWidth(substr($word, 0, $cut)) > $w) {
                        $cut--;
                    }
                    $out[] = substr($word, 0, $cut);
                    $word  = substr($word, $cut);
                }
                $line = $word;
            }
            $out[] = $line;
        }
        return $out;
    }

    /** A line cut to fit a width, with an ellipsis. Takes and returns encoded text. */
    private function clip(string $line, float $w): string
    {
        if ($this->GetStringWidth($line) <= $w) {
            return $line;
        }
        while ($line !== '' && $this->GetStringWidth($line . '...') > $w) {
            $line = substr($line, 0, -1);
        }
        return rtrim($line) . '...';
    }

    private function ink(array $c): void   { $this->SetTextColor($c[0], $c[1], $c[2]); }
    private function fill(array $c): void  { $this->SetFillColor($c[0], $c[1], $c[2]); }
    private function stroke(array $c): void { $this->SetDrawColor($c[0], $c[1], $c[2]); }

    /* ── page furniture ───────────────────────────────────────────────── */

    public function Header(): void
    {
        $pw = $this->GetPageWidth();
        if ($this->PageNo() === 1) {
            $this->fill(self::MAROON);
            $this->Rect(0, 0, $pw, 16, 'F');
            $this->fill(self::GOLD);
            $this->Rect(0, 16, $pw, 1.2, 'F');
            $this->ink([255, 255, 255]);
            $this->SetFont('Helvetica', 'B', 15);
            $this->SetXY(self::MARGIN_X, 4.2);
            $this->Cell(80, 8, 'PUPSync');
            $this->SetFont('Helvetica', '', 11);
            $this->SetXY($pw - self::MARGIN_X - 120, 4.6);
            $this->Cell(120, 8, 'Faculty Activity Report', 0, 0, 'R');
            $this->SetY(self::TOP + 2);
            return;
        }
        $this->stroke(self::MAROON);
        $this->SetLineWidth(0.4);
        $this->Line(self::MARGIN_X, 14, $pw - self::MARGIN_X, 14);
        $this->SetLineWidth(0.2);
        $this->ink(self::MAROON);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetXY(self::MARGIN_X, 8.5);
        $this->Cell(120, 4, $this->t('PUPSync · Faculty Activity Report'));
        $this->ink(self::MUTED);
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetXY($pw - self::MARGIN_X - 140, 8.5);
        $this->Cell(140, 4, $this->t($this->who), 0, 0, 'R');
        $this->SetY(self::TOP);
    }

    public function Footer(): void
    {
        $pw = $this->GetPageWidth();
        $y  = $this->GetPageHeight() - 10;
        $this->stroke(self::RULE);
        $this->Line(self::MARGIN_X, $y - 1.5, $pw - self::MARGIN_X, $y - 1.5);
        $this->ink(self::MUTED);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetXY(self::MARGIN_X, $y);
        $this->Cell(200, 4, $this->t('Generated ' . $this->stamp . ' (Philippine time) · Confidential: for the faculty member named in this report'));
        $this->SetXY($pw - self::MARGIN_X - 60, $y);
        $this->Cell(60, 4, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'R');
    }

    private function limitY(): float
    {
        return $this->GetPageHeight() - self::BOTTOM;
    }

    /** Start a new page when fewer than $h mm are left. Returns true when it did. */
    private function ensure(float $h): bool
    {
        if ($this->GetY() + $h > $this->limitY()) {
            $this->AddPage();
            return true;
        }
        return false;
    }

    /* ── section titles ───────────────────────────────────────────────── */

    private function section(string $title, string $sub = '', float $need = 34): void
    {
        $this->ensure($need);
        $this->SetY($this->GetY() + 3);
        $this->ink(self::MAROON);
        $this->SetFont('Helvetica', 'B', 12.5);
        $this->SetX(self::MARGIN_X);
        $this->Cell(0, 6, $this->t($title), 0, 1);
        $this->fill(self::GOLD);
        $this->Rect(self::MARGIN_X, $this->GetY() + 0.4, 18, 0.9, 'F');
        $this->SetY($this->GetY() + 3);
        if ($sub !== '') {
            $this->ink(self::MUTED);
            $this->SetFont('Helvetica', '', 8);
            $this->SetX(self::MARGIN_X);
            $lines = $this->wrap($sub, $this->GetPageWidth() - 2 * self::MARGIN_X);
            foreach ($lines as $ln) {
                $this->Cell(0, 3.8, $ln, 0, 1);
            }
            $this->SetY($this->GetY() + 1.5);
        }
    }

    /* ── page 1: who the faculty member is ────────────────────────────── */

    public function identityCard(array $p, string $term, string $generated): void
    {
        $x = self::MARGIN_X;
        $y = $this->GetY();
        $h = 30.0;
        $w = $this->GetPageWidth() - 2 * self::MARGIN_X;

        $this->fill([252, 247, 247]);
        $this->stroke(self::RULE);
        $this->Rect($x, $y, $w, $h, 'DF');

        $textX = $x + 5;
        if (!empty($p['photo'])) {
            try {
                $info = @getimagesize($p['photo']);
                if ($info && $info[0] > 0 && $info[1] > 0) {
                    $box = 24.0;
                    $r   = min($box / $info[0], $box / $info[1]);
                    $iw  = $info[0] * $r;
                    $ih  = $info[1] * $r;
                    $this->Image($p['photo'], $x + 4 + ($box - $iw) / 2, $y + 3 + ($box - $ih) / 2, $iw, $ih);
                    $this->stroke(self::RULE);
                    $this->Rect($x + 4, $y + 3, $box, $box);
                    $textX = $x + 4 + $box + 6;
                }
            } catch (Throwable $e) {
                error_log('[PUPSync report] photo skipped: ' . $e->getMessage());
            }
        }

        $this->ink(self::INK);
        $this->SetFont('Helvetica', 'B', 16);
        $this->SetXY($textX, $y + 4);
        $this->Cell(150, 8, $this->clip($this->t($p['name'] !== '' ? $p['name'] : 'Faculty member'), 150));

        $this->ink(self::MUTED);
        $this->SetFont('Helvetica', '', 9.5);
        $line = array_filter([
            'Faculty ID: ' . $p['faculty_id'],
            $p['department'] !== '' ? $p['department'] : '',
            $p['rank'] !== '' ? $p['rank'] : '',
        ], 'strlen');
        $this->SetXY($textX, $y + 13);
        $this->Cell(160, 5, $this->clip($this->t(implode('   |   ', $line)), 160));

        $this->SetFont('Helvetica', '', 8.5);
        $this->SetXY($textX, $y + 19.5);
        $this->Cell(160, 5, $this->t('Semester covered by the schedule: ' . $term));

        // right side: when it was made
        $this->ink(self::MUTED);
        $this->SetFont('Helvetica', '', 8);
        $this->SetXY($x + $w - 74, $y + 5);
        $this->Cell(70, 4, 'Report generated', 0, 1, 'R');
        $this->ink(self::INK);
        $this->SetFont('Helvetica', 'B', 9.5);
        $this->SetXY($x + $w - 74, $y + 9.5);
        $this->Cell(70, 5, $this->t($generated), 0, 1, 'R');

        $this->SetY($y + $h + 4);
    }

    public function glance(array $s, string $term): void
    {
        $gap   = 3.0;
        $n     = 5;
        $total = $this->GetPageWidth() - 2 * self::MARGIN_X;
        $w     = ($total - $gap * ($n - 1)) / $n;
        $h     = 22.0;
        $y     = $this->GetY();

        $boxes = [
            [(string)$s['equipment_total'], 'Equipment requests',
                $s['equipment_returned'] . ' returned · ' . $s['equipment_open'] . ' open · ' . $s['equipment_other'] . ' declined / cancelled'],
            [(string)$s['rooms_total'], 'Room & facility bookings',
                $s['rooms_completed'] . ' completed · ' . $s['rooms_open'] . ' open · ' . $s['rooms_other'] . ' declined / cancelled'],
            [(string)$s['students_total'], 'Student-authorized',
                'filed by students with your code'],
            [(string)$s['subjects'], 'Subjects this semester',
                $term],
            [fr_hours((int)$s['weekly_minutes']), 'Class hours per week',
                'from your weekly timetable'],
        ];
        foreach ($boxes as $i => [$big, $label, $small]) {
            $x = self::MARGIN_X + $i * ($w + $gap);
            $this->fill([255, 255, 255]);
            $this->stroke(self::RULE);
            $this->Rect($x, $y, $w, $h, 'DF');
            $this->fill(self::MAROON);
            $this->Rect($x, $y, 1.4, $h, 'F');
            $this->ink(self::MAROON);
            $this->SetFont('Helvetica', 'B', 17);
            $this->SetXY($x + 4, $y + 2);
            $this->Cell($w - 6, 8, $this->t($big));
            $this->ink(self::INK);
            $this->SetFont('Helvetica', 'B', 8);
            $this->SetXY($x + 4, $y + 10);
            $this->Cell($w - 6, 4, $this->clip($this->t($label), $w - 6));
            $this->ink(self::MUTED);
            $this->SetFont('Helvetica', '', 6.8);
            $lines = array_slice($this->wrap($small, $w - 6), 0, 2);
            foreach ($lines as $k => $ln) {
                $this->SetXY($x + 4, $y + 14.2 + $k * 3.1);
                $this->Cell($w - 6, 3, $ln);
            }
        }
        $this->SetY($y + $h + 2);
    }

    /** Label / value pairs, two pairs per row. */
    public function infoGrid(array $fields): void
    {
        $total = $this->GetPageWidth() - 2 * self::MARGIN_X;
        $lw    = 36.0;
        $vw    = $total / 2 - $lw;
        $pad   = 1.6;
        $rows  = array_chunk($fields, 2);

        foreach ($rows as $ri => $pair) {
            $cells = [];
            $rowH  = 0.0;
            foreach ($pair as $f) {
                $val = $f[1] !== '' ? $f[1] : '—';
                $this->SetFont('Helvetica', '', 9);
                $lines = $this->wrap($val, $vw - 2);
                $cells[] = [$f[0], $lines, $f[1] === ''];
                $rowH = max($rowH, count($lines) * 4.1);
            }
            $rowH += 2 * $pad;
            if ($this->ensure($rowH + 1)) {
                $this->SetY(self::TOP);
            }
            $y = $this->GetY();
            if ($ri % 2 === 0) {
                $this->fill(self::ZEBRA);
                $this->Rect(self::MARGIN_X, $y, $total, $rowH, 'F');
            }
            foreach ($cells as $ci => [$label, $lines, $blank]) {
                $x = self::MARGIN_X + $ci * ($total / 2);
                $this->ink(self::MUTED);
                $this->SetFont('Helvetica', 'B', 7.5);
                $this->SetXY($x + 2, $y + $pad + 0.4);
                $this->Cell($lw - 2, 4, $this->t($label));
                $this->ink($blank ? self::MUTED : self::INK);
                $this->SetFont('Helvetica', '', 9);
                foreach ($lines as $k => $ln) {
                    $this->SetXY($x + $lw, $y + $pad + $k * 4.1);
                    $this->Cell($vw, 4.1, $ln);
                }
            }
            $this->stroke(self::RULE);
            $this->Line(self::MARGIN_X, $y + $rowH, self::MARGIN_X + $total, $y + $rowH);
            $this->SetY($y + $rowH);
        }
    }

    /* ── tables ───────────────────────────────────────────────────────── */

    /**
     * @param list<array{0:string,1:float,2?:string}> $cols    label, width, align
     * @param list<array{cells:list<mixed>, tint?:array}> $rows  a cell is a string or ['t','sub','b','c','a']
     */
    public function table(array $cols, array $rows, string $empty): void
    {
        $total = 0.0;
        foreach ($cols as $c) {
            $total += $c[1];
        }
        if (!$rows) {
            $this->ink(self::MUTED);
            $this->SetFont('Helvetica', 'I', 9);
            $this->SetX(self::MARGIN_X);
            $this->Cell(0, 8, $this->t($empty), 0, 1);
            return;
        }

        $this->ensure(24);               // never leave a header row alone at the bottom of a page
        $this->tableHead($cols);
        $pad = 1.3;
        foreach ($rows as $i => $row) {
            // measure
            $prepared = [];
            $rowH     = 0.0;
            foreach ($row['cells'] as $ci => $cell) {
                $cell = is_array($cell) ? $cell : ['t' => (string)$cell];
                $w    = $cols[$ci][1] - 3;
                $this->SetFont('Helvetica', !empty($cell['b']) ? 'B' : '', 8);
                $main = $this->wrap((string)($cell['t'] ?? ''), $w);
                $this->SetFont('Helvetica', '', 6.6);
                $sub  = !empty($cell['sub']) ? array_slice($this->wrap((string)$cell['sub'], $w), 0, 3) : [];
                $prepared[] = [$cell, $main, $sub];
                $rowH = max($rowH, count($main) * 3.7 + count($sub) * 3.0);
            }
            $rowH += 2 * $pad;

            if ($this->GetY() + $rowH > $this->limitY()) {
                $this->AddPage();
                $this->tableHead($cols);
            }
            $y = $this->GetY();
            if (!empty($row['tint'])) {
                $this->fill($row['tint']);
                $this->Rect(self::MARGIN_X, $y, $total, $rowH, 'F');
            } elseif ($i % 2 === 1) {
                $this->fill(self::ZEBRA);
                $this->Rect(self::MARGIN_X, $y, $total, $rowH, 'F');
            }
            $x = self::MARGIN_X;
            foreach ($prepared as $ci => [$cell, $main, $sub]) {
                $w     = $cols[$ci][1];
                $align = $cell['a'] ?? ($cols[$ci][2] ?? 'L');
                $color = $cell['c'] ?? self::INK;
                $this->ink($color);
                $this->SetFont('Helvetica', !empty($cell['b']) ? 'B' : '', 8);
                $cy = $y + $pad;
                foreach ($main as $ln) {
                    $this->SetXY($x + 1.5, $cy);
                    $this->Cell($w - 3, 3.7, $ln, 0, 0, $align);
                    $cy += 3.7;
                }
                if ($sub) {
                    $this->ink(self::MUTED);
                    $this->SetFont('Helvetica', '', 6.6);
                    foreach ($sub as $ln) {
                        $this->SetXY($x + 1.5, $cy);
                        $this->Cell($w - 3, 3.0, $ln, 0, 0, $align);
                        $cy += 3.0;
                    }
                }
                $x += $w;
            }
            $this->stroke(self::RULE);
            $this->Line(self::MARGIN_X, $y + $rowH, self::MARGIN_X + $total, $y + $rowH);
            $this->SetY($y + $rowH);
        }
    }

    private function tableHead(array $cols): void
    {
        $total = 0.0;
        foreach ($cols as $c) {
            $total += $c[1];
        }
        $y = $this->GetY();
        $h = 6.4;
        $this->fill(self::MAROON);
        $this->Rect(self::MARGIN_X, $y, $total, $h, 'F');
        $this->ink([255, 255, 255]);
        $this->SetFont('Helvetica', 'B', 7.4);
        $x = self::MARGIN_X;
        foreach ($cols as $c) {
            $this->SetXY($x + 1.5, $y + 1.2);
            $this->Cell($c[1] - 3, 4, $this->clip($this->t($c[0]), $c[1] - 3), 0, 0, $c[2] ?? 'L');
            $x += $c[1];
        }
        $this->SetY($y + $h);
    }

    /** Status word in a colour (the same meaning the My Activity chips use). */
    public static function statusColor(string $status): array
    {
        switch ($status) {
            case 'Returned':
            case 'Completed':
            case 'Approved':
                return [0, 110, 50];
            case 'Returned late':
            case 'Pending':
                return [170, 100, 0];
            case 'Overdue':
            case 'Declined':
                return [175, 0, 0];
            case 'On Loan':
            case 'In progress':
            case 'Upcoming':
                return [20, 70, 150];
            default:                                   // Cancelled and anything new
                return [90, 90, 90];
        }
    }

    public function legendStudent(string $text): void
    {
        $y = $this->GetY();
        $this->fill(self::STUDENT);
        $this->stroke(self::RULE);
        $this->Rect(self::MARGIN_X, $y + 0.6, 6, 3.4, 'DF');
        $this->ink(self::MUTED);
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetXY(self::MARGIN_X + 8, $y + 0.4);
        $this->Cell(0, 4, $this->t($text));
        $this->SetY($y + 6);
    }

    /* ── the weekly timetable ─────────────────────────────────────────── */

    public function timetable(array $meetings): void
    {
        $left   = self::MARGIN_X;
        $total  = $this->GetPageWidth() - 2 * self::MARGIN_X;
        $timeW  = 15.0;
        $dayW   = ($total - $timeW) / 6;
        $days   = [1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday'];

        // hour range that holds every class (at least 8 hours, so a light week still looks like a timetable)
        $minS = 24 * 60;
        $maxE = 0;
        foreach ($meetings as $m) {
            $minS = min($minS, self::mins($m['start']));
            $maxE = max($maxE, self::mins($m['end']));
        }
        $startH = (int)floor($minS / 60);
        $endH   = (int)ceil($maxE / 60);
        while ($endH - $startH < 8) {
            if ($endH < 21) { $endH++; } elseif ($startH > 6) { $startH--; } else { break; }
        }
        $hours = $endH - $startH;

        $headH = 7.0;
        $top   = $this->GetY();
        $avail = $this->limitY() - $top - $headH - 2;
        $hourH = max(7.5, min(16.0, $avail / $hours));

        // day headers
        $this->fill(self::MAROON);
        $this->Rect($left, $top, $total, $headH, 'F');
        $this->ink([255, 255, 255]);
        $this->SetFont('Helvetica', 'B', 8.5);
        $this->SetXY($left, $top + 1.4);
        $this->Cell($timeW, 4, 'Time', 0, 0, 'C');
        foreach ($days as $d => $name) {
            $this->SetXY($left + $timeW + ($d - 1) * $dayW, $top + 1.4);
            $this->Cell($dayW, 4, $name, 0, 0, 'C');
        }

        // grid
        $gy = $top + $headH;
        $this->stroke(self::RULE);
        $this->SetLineWidth(0.15);
        for ($h = 0; $h <= $hours; $h++) {
            $y = $gy + $h * $hourH;
            $this->Line($left, $y, $left + $total, $y);
            if ($h < $hours) {
                $this->ink(self::MUTED);
                $this->SetFont('Helvetica', '', 7);
                $label = date('g A', strtotime(sprintf('%02d:00', ($startH + $h) % 24)));
                $this->SetXY($left, $y + 0.8);
                $this->Cell($timeW - 1.5, 3, $label, 0, 0, 'R');
            }
        }
        for ($d = 0; $d <= 6; $d++) {
            $x = $left + $timeW + $d * $dayW;
            $this->Line($x, $gy, $x, $gy + $hours * $hourH);
        }
        $this->Line($left, $gy, $left, $gy + $hours * $hourH);
        $this->SetLineWidth(0.2);

        // colour per subject
        $colorOf = [];
        foreach ($meetings as $m) {
            $k = mb_strtolower($m['code'] . '|' . $m['subject']);
            if (!isset($colorOf[$k])) {
                $colorOf[$k] = count($colorOf) % count(self::BLOCKS);
            }
        }

        // blocks, side by side when two classes overlap
        foreach ($days as $d => $_name) {
            $dm = array_values(array_filter($meetings, static fn(array $m): bool => $m['dow'] === $d));
            usort($dm, static fn(array $a, array $b): int => self::mins($a['start']) <=> self::mins($b['start']));
            $lanes   = [];      // lane index -> end minute
            $cluster = [];      // meetings in the current overlapping run
            $flush = function () use (&$cluster, &$lanes, $left, $timeW, $dayW, $gy, $startH, $hourH, $d, $colorOf): void {
                if (!$cluster) {
                    return;
                }
                $n = max(array_column($cluster, 'lane')) + 1;
                foreach ($cluster as $m) {
                    $laneW = $dayW / $n;
                    $x = $left + $timeW + ($d - 1) * $dayW + $m['lane'] * $laneW;
                    $y = $gy + (self::mins($m['start']) - $startH * 60) / 60 * $hourH;
                    $h = $m['minutes'] / 60 * $hourH;
                    $this->block($x + 0.4, $y + 0.3, $laneW - 0.8, $h - 0.6, $m, self::BLOCKS[$colorOf[mb_strtolower($m['code'] . '|' . $m['subject'])]]);
                }
                $cluster = [];
                $lanes   = [];
            };
            foreach ($dm as $m) {
                $s = self::mins($m['start']);
                if ($cluster && $s >= max($lanes)) {
                    $flush();
                }
                $lane = 0;
                while (isset($lanes[$lane]) && $lanes[$lane] > $s) {
                    $lane++;
                }
                $lanes[$lane] = self::mins($m['end']);
                $m['lane']    = $lane;
                $cluster[]    = $m;
            }
            $flush();
        }
        $this->SetY($gy + $hours * $hourH + 2);
    }

    private function block(float $x, float $y, float $w, float $h, array $m, array $color): void
    {
        $this->fill($color[0]);
        $this->stroke($color[1]);
        $this->SetLineWidth(0.25);
        $this->Rect($x, $y, $w, $h, 'DF');
        $this->SetLineWidth(0.2);
        $this->fill($color[1]);
        $this->Rect($x, $y, 1.1, $h, 'F');

        $tw   = $w - 4.2;
        $head = trim($m['code'] . ($m['section'] !== '' ? ' · ' . $m['section'] : ''), ' ·');
        $comp = $m['component'] !== '' ? $m['component'] . ($m['group'] !== '' ? ' (' . $m['group'] . ')' : '') : '';
        $this->SetFont('Helvetica', 'B', 7);
        $l1 = $this->wrap($head !== '' ? $head : 'Class', $tw);
        $this->SetFont('Helvetica', '', 6.4);
        $l2 = $m['subject'] !== '' ? $this->wrap($m['subject'], $tw) : [];
        $l3 = $this->wrap(trim($m['room'] . ($comp !== '' ? ' · ' . $comp : ''), ' ·'), $tw);
        $l4 = [$this->t(fr_time($m['start']) . ' - ' . fr_time($m['end']))];

        // lines in priority order, each with its style; as many as the block is tall
        $queue = [];
        foreach ($l1 as $l) { $queue[] = [$l, 'B', 7, self::INK]; }
        foreach ($l2 as $l) { $queue[] = [$l, '', 6.4, self::INK]; }
        foreach ($l3 as $l) { $queue[] = [$l, '', 6.4, self::MUTED]; }
        foreach ($l4 as $l) { $queue[] = [$l, '', 6.4, self::MUTED]; }

        $room = (int)floor(($h - 1.2) / 2.9);
        if ($room < 1) {
            return;
        }
        $info = count($l1) + count($l2) + count($l3);        // everything except the time line
        if ($room >= $info) {
            $queue = array_slice($queue, 0, $room);           // the time line only shows when there is room for it
        } else {
            $queue = array_slice($queue, 0, $room);           // cut from the bottom and mark that something is missing
            $last  = $room - 1;
            $queue[$last][0] = $this->clipMark($queue[$last][0], $tw, $queue[$last][1], (float)$queue[$last][2]);
        }
        $cy = $y + 0.9;
        foreach ($queue as [$line, $style, $size, $col]) {
            $this->ink($col);
            $this->SetFont('Helvetica', $style, $size);
            $this->SetXY($x + 2.4, $cy);
            $this->Cell($tw, 2.9, $this->clip($line, $tw));
            $cy += 2.9;
        }
    }

    private function clipMark(string $line, float $w, string $style, float $size): string
    {
        $this->SetFont('Helvetica', $style, $size);
        return $this->clip($line . ' ...', $w);
    }

    private static function mins(string $hm): int
    {
        $p = explode(':', $hm);
        return ((int)($p[0] ?? 0)) * 60 + (int)($p[1] ?? 0);
    }

    /* ── closing note ─────────────────────────────────────────────────── */

    public function closing(array $notes, array $truncated): void
    {
        $this->ensure(26);
        $this->SetY($this->GetY() + 4);
        $lines = [];
        if ($notes) {
            $lines[] = 'These parts could not be read and may be incomplete: ' . implode(', ', $notes) . '.';
        }
        if ($truncated) {
            $lines[] = 'Only the latest ' . FR_ROW_LIMIT . ' rows are printed for: ' . implode(', ', $truncated) . '.';
        }
        $lines[] = 'This report lists your account details and everything recorded in PUPSync under your name, including the equipment and rooms students filed with your code. Your password, security tokens and QR codes are never included.';
        $this->ink(self::MUTED);
        $this->SetFont('Helvetica', 'I', 8);
        foreach ($lines as $l) {
            foreach ($this->wrap($l, $this->GetPageWidth() - 2 * self::MARGIN_X) as $ln) {
                $this->SetX(self::MARGIN_X);
                $this->Cell(0, 4, $ln, 0, 1);
            }
            $this->SetY($this->GetY() + 1);
        }
        $this->ink(self::MAROON);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetX(self::MARGIN_X);
        $this->Cell(0, 6, '- End of report -', 0, 1, 'C');
    }

    /** Page break that keeps the cursor under the running header. */
    public function newPage(): void
    {
        $this->AddPage();
    }

    public function sectionTitle(string $title, string $sub = '', float $need = 34): void
    {
        $this->section($title, $sub, $need);
    }
}

/* ══════════════════════════════════════════════════════════════════════════
 * Assemble the whole report
 * ══════════════════════════════════════════════════════════════════════════ */

function fr_render_pdf(array $report): string
{
    $p   = $report['profile'];
    $sch = $report['schedule'];
    $S   = $report['summary'];
    $stamp = date('M j, Y, g:i A', (int)strtotime($report['generated_at']));

    $pdf = new FacultyReportPdf();
    $pdf->SetTitle('PUPSync Faculty Activity Report - ' . $p['name'], true);
    $pdf->SetAuthor('PUPSync', true);
    $pdf->SetCreator('PUPSync', true);
    $pdf->setMeta($p['name'] . ($p['faculty_id'] !== '' ? '  (' . $p['faculty_id'] . ')' : ''), $stamp);
    $pdf->AddPage();

    /* 1. who */
    $pdf->identityCard($p, $sch['term'], $stamp);
    $pdf->glance($S, $sch['term']);
    $pdf->sectionTitle('Faculty Information', 'Your account details as saved in PUPSync.', 38);
    $pdf->infoGrid($p['fields']);

    /* 2. equipment */
    $rows = [];
    foreach ($report['equipment'] as $e) {
        $by = $e['student'] === ''
            ? ['t' => 'You']
            : ['t' => 'Student: ' . $e['student'], 'sub' => $e['student_id'] !== '' ? 'ID ' . $e['student_id'] : '', 'b' => true];
        $sub = $e['picked_up'] !== '' ? 'Picked up ' . $e['picked_up'] : '';
        $rows[] = [
            'cells' => [
                '#' . $e['id'],
                ['t' => $e['item'], 'b' => true, 'sub' => $e['extended'] > 0 ? 'Extended ' . $e['extended'] . ' time' . ($e['extended'] > 1 ? 's' : '') : ''],
                ['t' => (string)$e['qty'], 'a' => 'C'],
                ['t' => $e['day'] !== '' ? $e['day'] : '—', 'sub' => $e['requested'] !== '' ? 'Requested ' . $e['requested'] : ''],
                ['t' => $e['time'] !== '' ? $e['time'] : '—', 'sub' => $sub],
                ['t' => $e['status'], 'b' => true, 'c' => FacultyReportPdf::statusColor($e['status']), 'sub' => $e['reason']],
                $e['returned_at'] !== '' ? $e['returned_at'] : '—',
                $e['where'] !== '' ? $e['where'] : '—',
                $by,
            ],
            'tint' => $e['student'] !== '' ? [255, 246, 214] : null,
        ];
    }
    $pdf->sectionTitle(
        'Equipment Borrowed',
        'Every equipment request under your name, newest first, with its day, time, status and the moment it was returned.',
        38
    );
    if ($S['students_total'] > 0) {
        $pdf->legendStudent('Highlighted rows were filed by a student with your code.');
    }
    $pdf->table(
        [['Req #', 12], ['Equipment', 46], ['Qty', 10, 'C'], ['Day', 30], ['Time', 33], ['Status', 24], ['Returned on', 32], ['Location', 36], ['Filed by', 36]],
        $rows,
        'No equipment requests yet.'
    );

    /* 3. rooms */
    $rows = [];
    foreach ($report['rooms'] as $r) {
        $by = $r['student'] === ''
            ? ['t' => 'You']
            : ['t' => 'Student: ' . $r['student'], 'sub' => $r['student_id'] !== '' ? 'ID ' . $r['student_id'] : '', 'b' => true];
        $rows[] = [
            'cells' => [
                '#' . $r['id'],
                ['t' => $r['room'], 'b' => true, 'sub' => $r['building']],
                ['t' => $r['day'] !== '' ? $r['day'] : '—', 'sub' => $r['requested'] !== '' ? 'Requested ' . $r['requested'] : ''],
                $r['time'] !== '' ? $r['time'] : '—',
                $r['purpose'] !== '' ? $r['purpose'] : '—',
                ['t' => $r['status'], 'b' => true, 'c' => FacultyReportPdf::statusColor($r['status']), 'sub' => $r['reason']],
                ['t' => $r['closed_at'] !== '' ? $r['closed_at'] : '—', 'sub' => $r['closed_lbl']],
                $by,
            ],
            'tint' => $r['student'] !== '' ? [255, 246, 214] : null,
        ];
    }
    $pdf->sectionTitle(
        'Rooms & Facilities Reserved',
        'Bookings you (or a student with your code) made through PUPSync, separate from the rooms your classes are assigned to (see the timetable below).',
        38
    );
    $pdf->table(
        [['Res #', 12], ['Room / Facility', 46], ['Day', 33], ['Time', 34], ['Purpose', 46], ['Status', 24], ['Completed on', 31], ['Filed by', 33]],
        $rows,
        'No room or facility reservations yet.'
    );

    /* 4. student-authorized */
    $rows = [];
    foreach ($report['students'] as $s) {
        $rows[] = ['cells' => [
            ['t' => $s['type'], 'b' => true],
            ['t' => $s['student'], 'b' => true],
            $s['student_id'] !== '' ? $s['student_id'] : '—',
            $s['what'],
            $s['when'] !== '' ? $s['when'] : '—',
            ['t' => $s['status'], 'b' => true, 'c' => FacultyReportPdf::statusColor($s['status'])],
            $s['done_at'] !== '' ? $s['done_at'] : '—',
        ]];
    }
    $pdf->sectionTitle(
        'Student-Authorized Transactions',
        'Equipment and rooms that students requested using your authorization. You remain responsible for these; they also appear in the tables above.',
        38
    );
    $pdf->table(
        [['Type', 20], ['Student', 48], ['Student ID', 32], ['Equipment / Room', 54], ['When', 45], ['Status', 24], ['Returned / done on', 36]],
        $rows,
        'No student has filed anything with your authorization.'
    );

    /* 5. weekly timetable (own page: it needs the full height) */
    $pdf->newPage();
    $pdf->sectionTitle('Weekly Class Timetable', $sch['term'] . ($sch['term_imported'] ? '' : '  (semester worked out from today\'s date)'), 20);
    if ($sch['meetings']) {
        $pdf->timetable($sch['meetings']);
    } else {
        $pdf->table([['', 100]], [], 'No class schedule has been imported for your account yet. Ask the administrator to import the Rooms Assigned file.');
    }

    /* 6. subjects */
    $rows = [];
    foreach ($sch['subjects'] as $s) {
        $rows[] = ['cells' => [
            ['t' => $s['code'] !== '' ? $s['code'] : '—', 'b' => true],
            $s['subject'] !== '' ? $s['subject'] : '—',
            $s['section'] !== '' ? $s['section'] : '—',
            $s['component'],
            ['t' => $s['room'] !== '' ? $s['room'] : '—', 'sub' => $s['building']],
            $s['when'],
            ['t' => fr_hours((int)$s['minutes']), 'a' => 'R'],
        ]];
    }
    $pdf->sectionTitle(
        'Subjects This Semester',
        $sch['term'] . ($sch['weekly_minutes'] > 0 ? '  ·  ' . fr_hours((int)$sch['weekly_minutes']) . ' class hours per week in total' : ''),
        38
    );
    $pdf->table(
        [['Code', 26], ['Subject', 64], ['Section', 28], ['Type', 26], ['Room', 50], ['Day & time', 48], ['Hrs/wk', 17, 'R']],
        $rows,
        'No subjects are linked to your account yet.'
    );

    $pdf->closing($report['notes'], $report['truncated']);
    return $pdf->Output('S');
}
