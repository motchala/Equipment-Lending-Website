<?php

declare(strict_types=1);

/**
 * reports/core/pdf.php
 *
 * PDF renderer for the Generated Reports feature.
 * Input : the array returned by rpt_generate() in reports/core/data.php.
 * Output: a PDF file as a binary string (use with header() + echo in the API endpoint).
 *
 * Layout matches the official PUP Biñan Campus letter template:
 *   - Letterhead: Republic · PUP · Office of VP for Campuses · Biñan Campus
 *   - Double-rule separator under letterhead
 *   - Address bar
 *   - Disclaimer box (not an official PUP document)
 *   - Report title + period centred below
 *   - Maroon column headers, alternating rose-tinted data rows
 *   - Footer: campus + "Internal Use Only" | page number
 *
 * Uses FPDF (vendor/fpdf/fpdf.php) — already vendored.
 * No DB calls, no session access.
 *
 * Public interface (unchanged):
 *   rpt_render_pdf(array $data): string
 *   rpt_render_pdf_multi(array $datasets): string
 */

require_once __DIR__ . '/../../vendor/fpdf/fpdf.php';

class ReportPdf extends FPDF
{
    // ── Layout constants ───────────────────────────────────────────────────
    public const  MARGIN      = 14.0;   // left / right margin (mm)
    private const HEADER_H    = 52.0;   // total height reserved for the header area (mm)

    // ── Colour palette ─────────────────────────────────────────────────────
    private const MAROON      = [128,  0,   0];
    private const MAROON_DK   = [ 96,  0,   0];   // double-rule top line
    private const AMBER_BG    = [255, 248, 225];   // disclaimer fill
    private const AMBER_BD    = [249, 168,  37];   // disclaimer border
    private const AMBER_TXT   = [ 93,  64,  33];   // disclaimer text
    private const ROSE_ROW    = [253, 244, 244];   // odd data row
    private const GREY_LN     = [220, 215, 215];   // row divider
    private const TEXT        = [ 30,  30,  30];
    private const SUBTEXT     = [100, 100, 100];

    // ── Per-report properties (set by callers) ─────────────────────────────
    public string $rptTitle  = '';
    public string $rptRange  = '';
    public string $rptGenAt  = '';

    // ── FPDF Header callback — redrawn at the top of every page ───────────
    // Layout mirrors the official PUP Biñan Campus letter template:
    //   [ seal placeholder | centred text block | Bagong Pilipinas placeholder ]
    //   thin maroon rule
    //   disclaimer box
    //   report title + meta line
    //   maroon rule
    public function Header(): void
    {
        $W    = $this->GetPageWidth();
        $M    = self::MARGIN;
        $yTop = 8.0;   // top of letterhead block

        // ── Logo dimensions — match Word template proportions ─────────────
        $logoW  = 33.0;   // PUP seal: match template height (~4-line text block)
        $rightW = 28.0;   // Bagong Pilipinas: wider to match template visual weight
        $rightH = 30.0;
        $gap    = 3.0;    // gap between logo and text column
        $textW  = $W - 2 * $M - $logoW - $gap - $rightW - $gap;
        $textX  = $M + $logoW + $gap;

        // ── Left logo: PUP seal ───────────────────────────────────────────
        $sealPath = __DIR__ . '/../../assets/images/pup-seal.png';
        if (is_file($sealPath)) {
            $this->Image($sealPath, $M, $yTop, $logoW, $logoW);
        }

        // ── Right logo: Bagong Pilipinas ──────────────────────────────────
        $rLogoX = $W - $M - $rightW;
        $bpPath = __DIR__ . '/../../assets/images/bagong-pilipinas.png';
        if (is_file($bpPath)) {
            $this->Image($bpPath, $rLogoX, $yTop, $rightW, $rightH);
        }

        // ── Text block — vertically centred against the seal, left-aligned ──
        // Total text height: 4.5 + 7 + 5 + 5.5 = 22mm. Offset from top to centre in 33mm seal.
        $textYOffset = ($logoW - 22.0) / 2;   // centres the 22mm text block in the seal height
        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...self::SUBTEXT);
        $this->SetXY($textX, $yTop + $textYOffset);
        $this->Cell($textW, 4.5, $this->enc('REPUBLIC OF THE PHILIPPINES'), 0, 1, 'L');

        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(...self::MAROON);
        $this->SetX($textX);
        $this->Cell($textW, 7, $this->enc('POLYTECHNIC UNIVERSITY OF THE PHILIPPINES'), 0, 1, 'L');

        $this->SetFont('Helvetica', '', 8.5);
        $this->SetTextColor(...self::SUBTEXT);
        $this->SetX($textX);
        $this->Cell($textW, 5, $this->enc('OFFICE OF THE VICE PRESIDENT FOR CAMPUSES'), 0, 1, 'L');

        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::TEXT);
        $this->SetX($textX);
        $this->Cell($textW, 5.5, $this->enc('BINAN CAMPUS'), 0, 1, 'L');

        // ── Double rule under letterhead block ────────────────────────────
        $yRule1 = $yTop + $logoW + 1.5;
        $this->SetDrawColor(...self::MAROON);
        $this->SetLineWidth(0.4);
        $this->Line($M, $yRule1, $W - $M, $yRule1);
        $this->SetLineWidth(0.15);
        $this->Line($M, $yRule1 + 1.2, $W - $M, $yRule1 + 1.2);

        // ── Disclaimer box ────────────────────────────────────────────────
        $yDisc = $yRule1 + 3.5;
        $boxH  = 10.0;
        $this->SetFillColor(...self::AMBER_BG);
        $this->SetDrawColor(...self::AMBER_BD);
        $this->SetLineWidth(0.2);
        $this->Rect($M, $yDisc, $W - 2 * $M, $boxH, 'FD');
        // Left accent bar
        $this->SetFillColor(...self::AMBER_BD);
        $this->Rect($M, $yDisc, 2.0, $boxH, 'F');

        $this->SetFont('Helvetica', 'B', 6.5);
        $this->SetTextColor(...self::AMBER_TXT);
        $this->SetXY($M + 3.0, $yDisc + 1.8);
        $this->Cell(14, 3, $this->enc('DISCLAIMER:'), 0, 0, 'L');

        $this->SetFont('Helvetica', 'I', 6.5);
        $this->SetXY($M + 18, $yDisc + 1.8);
        $this->MultiCell(
            $W - 2 * $M - 19, 3.3,
            $this->enc(
                'This report is generated by the PUPSync Equipment Lending and Room Reservation System '
                . 'and is for INTERNAL REFERENCE ONLY. This is NOT an official document of the '
                . 'Polytechnic University of the Philippines. For official records, please coordinate '
                . 'with the university administration.'
            ),
            0, 'L'
        );

        // ── Report title block ────────────────────────────────────────────
        $yTitle = $yDisc + $boxH + 4;
        $this->SetFont('Helvetica', 'B', 12);
        $this->SetTextColor(...self::MAROON);
        $this->SetXY($M, $yTitle);
        $this->Cell(0, 6, $this->enc(strtoupper($this->rptTitle)), 0, 1, 'C');

        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::SUBTEXT);
        $this->SetX($M);
        $meta = $this->rptRange !== '' ? 'Period: ' . $this->rptRange . '   |   ' : '';
        $meta .= 'Generated: ' . $this->rptGenAt;
        $this->Cell(0, 4, $this->enc($meta), 0, 1, 'C');

        // Rule under title
        $yRule2 = $this->GetY() + 1;
        $this->SetDrawColor(...self::MAROON);
        $this->SetLineWidth(0.5);
        $this->Line($M, $yRule2, $W - $M, $yRule2);
        $this->Ln(4);
    }

    // ── FPDF Footer callback — matches PUP letter template bottom zone ────
    // Left : address + direct line + website + email + motto
    // Right: page number (replaces accreditation badges — cannot render images here)
    public function Footer(): void
    {
        $M = self::MARGIN;
        $W = $this->GetPageWidth();

        // Footer zone: 3 address lines (3.8mm each) + 2 motto lines (5mm each) = ~21.4mm
        $this->SetY(-24);

        $usable = $W - 2 * $M;
        $leftW  = $usable * 0.44;
        $badgeH = 20.0;
        $badgeW = round($badgeH * 5.4);   // ~108mm proportional to actual image aspect ratio
        $rightX = $W - $M - $badgeW;
        $rightW = $badgeW;

        // Record the Y where footer content starts — badge top aligns here
        $footerStartY = $this->GetY();

        // ── Left: address block matching the template exactly ─────────────
        $this->SetFont('Helvetica', '', 7.5);
        $this->SetTextColor(...self::SUBTEXT);
        $this->SetX($M);
        $this->Cell($leftW, 3.8, $this->enc('Barangay Zapote, Binan City, Laguna 4024'), 0, 1, 'L');

        $this->SetX($M);
        $this->Cell($leftW, 3.8, $this->enc('Direct Line: (049) 544-0627'), 0, 1, 'L');

        $this->SetX($M);
        $this->Cell($leftW, 3.8, $this->enc('Website: www.pup.edu.ph  |  Email: binan@pup.edu.ph'), 0, 1, 'L');

        // Motto — two lines, larger bold to match template's prominent style
        $this->SetFont('Helvetica', 'B', 10);
        $this->SetTextColor(...self::MAROON);
        $this->SetX($M);
        $this->Cell($leftW, 5, $this->enc('A LEADING COMPREHENSIVE'), 0, 1, 'L');

        $this->SetX($M);
        $this->Cell($leftW, 5, $this->enc('POLYTECHNIC UNIVERSITY IN ASIA'), 0, 0, 'L');

        // ── Right: accreditation badges — top aligned with first address line ──
        $badgePath = __DIR__ . '/../../assets/images/pup-accreditation.png';
        if (is_file($badgePath)) {
            $this->Image($badgePath, $rightX, $footerStartY, $rightW, $badgeH);
        } else {
            $this->SetFont('Helvetica', '', 7);
            $this->SetTextColor(...self::SUBTEXT);
            $this->SetXY($rightX, $footerStartY);
            $this->Cell($rightW, 3.5, $this->enc('Page ' . $this->PageNo()), 0, 0, 'R');
        }
    }

    // ── Data table ─────────────────────────────────────────────────────────
    public function buildTable(array $cols, array $rows): void
    {
        if (!$cols) {
            return;
        }

        $pageW = $this->GetPageWidth() - 2 * self::MARGIN;
        $n     = count($cols);
        $colW  = array_fill(0, $n, $pageW / $n);

        // Column header row
        $this->SetFillColor(...self::MAROON);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetDrawColor(...self::MAROON_DK);
        $this->SetLineWidth(0.1);
        $this->SetX(self::MARGIN);
        foreach ($cols as $i => $label) {
            $this->Cell($colW[$i], 7, $this->enc((string)$label), 1, 0, 'L', true);
        }
        $this->Ln();

        // Data rows
        $this->SetFont('Helvetica', '', 8);
        $this->SetDrawColor(...self::GREY_LN);
        $this->SetLineWidth(0.1);
        $odd = true;
        foreach ($rows as $row) {
            if ($this->GetY() > $this->GetPageHeight() - 30) {
                $this->AddPage();
            }
            $this->SetFillColor(...($odd ? self::ROSE_ROW : [255, 255, 255]));
            $this->SetTextColor(...self::TEXT);
            $this->SetX(self::MARGIN);
            foreach ($cols as $i => $_) {
                $cell = isset($row[$i]) ? (string)$row[$i] : '';
                $this->Cell($colW[$i], 6, $this->enc($cell), 'B', 0, 'L', true);
            }
            $this->Ln();
            $odd = !$odd;
        }

        // Bottom rule
        $this->SetDrawColor(...self::MAROON);
        $this->SetLineWidth(0.3);
        $this->Line(self::MARGIN, $this->GetY(), $this->GetPageWidth() - self::MARGIN, $this->GetY());
        $this->Ln(2);

        // Record count
        $this->SetFont('Helvetica', 'I', 7);
        $this->SetTextColor(...self::SUBTEXT);
        $this->SetX(self::MARGIN);
        $this->Cell(0, 5, $this->enc(count($rows) . ' record(s)'), 0, 1, 'L');
    }

    /** Convert UTF-8 string to Windows-1252 for FPDF built-in fonts. */
    private function enc(string $s): string
    {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }
}

// ── Public renderers (signatures unchanged — generate.php needs no edits) ──

/**
 * Render multiple datasets into one PDF, one dataset per page.
 *
 * @param  array[] $datasets   Each element is a rpt_generate() envelope.
 * @return string              Raw PDF bytes.
 */
function rpt_render_pdf_multi(array $datasets): string
{
    if (count($datasets) === 1) {
        return rpt_render_pdf($datasets[0]);
    }

    // Header height grows because of the letterhead; bump the top margin.
    $pdf = new ReportPdf('L', 'mm', 'A4');
    $pdf->SetAutoPageBreak(true, 28);
    $pdf->SetMargins(ReportPdf::MARGIN, 78, ReportPdf::MARGIN);

    foreach ($datasets as $data) {
        $pdf->rptTitle = $data['title']        ?? 'Report';
        $pdf->rptRange = $data['date_range']   ?? '';
        $pdf->rptGenAt = $data['generated_at'] ?? date('Y-m-d H:i:s');
        $pdf->AddPage();
        $pdf->buildTable($data['cols'] ?? [], $data['rows'] ?? []);
    }

    return $pdf->Output('S');
}

/**
 * Render a single dataset as a PDF.
 *
 * @param  array $data   rpt_generate() envelope.
 * @return string        Raw PDF bytes.
 */
function rpt_render_pdf(array $data): string
{
    $pdf = new ReportPdf('L', 'mm', 'A4');
    $pdf->rptTitle = $data['title']        ?? 'Report';
    $pdf->rptRange = $data['date_range']   ?? '';
    $pdf->rptGenAt = $data['generated_at'] ?? date('Y-m-d H:i:s');

    $pdf->SetAutoPageBreak(true, 28);
    $pdf->SetMargins(ReportPdf::MARGIN, 78, ReportPdf::MARGIN);
    $pdf->AddPage();
    $pdf->buildTable($data['cols'] ?? [], $data['rows'] ?? []);

    return $pdf->Output('S');
}
