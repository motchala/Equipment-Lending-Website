<?php

declare(strict_types=1);

/**
 * reports/core/pdf.php
 *
 * PDF renderer for the Generated Reports feature.
 * Input : the array returned by rpt_generate() in reports/core/data.php.
 * Output: a PDF file as a binary string (use with header() + echo in the API endpoint).
 *
 * Uses FPDF (vendor/fpdf/fpdf.php) — already vendored.
 * No DB calls, no session access.
 */

require_once __DIR__ . '/../../vendor/fpdf/fpdf.php';

class ReportPdf extends FPDF
{
    public const MARGIN   = 12.0;
    private const MAROON   = [128, 0, 0];
    private const GREY_BG  = [245, 245, 245];
    private const GREY_LN  = [220, 220, 220];
    private const TEXT     = [30, 30, 30];

    public string $rptTitle    = '';
    public string $rptRange    = '';
    public string $rptGenAt    = '';

    public function Header(): void
    {
        // Maroon top bar
        $this->SetFillColor(...self::MAROON);
        $this->Rect(0, 0, $this->GetPageWidth(), 8, 'F');

        $this->SetFont('Helvetica', 'B', 13);
        $this->SetTextColor(...self::MAROON);
        $this->SetXY(self::MARGIN, 11);
        $this->Cell(0, 6, $this->enc($this->rptTitle), 0, 1, 'L');

        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->SetX(self::MARGIN);
        $this->Cell(0, 5, $this->enc('Period: ' . $this->rptRange . '   |   Generated: ' . $this->rptGenAt), 0, 1, 'L');

        // Thin divider
        $this->SetDrawColor(...self::GREY_LN);
        $this->SetLineWidth(0.3);
        $this->Line(self::MARGIN, $this->GetY() + 1, $this->GetPageWidth() - self::MARGIN, $this->GetY() + 1);
        $this->Ln(4);
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont('Helvetica', '', 7);
        $this->SetTextColor(150, 150, 150);
        $this->Cell(0, 5, 'PUPSync Binan Campus   |   Page ' . $this->PageNo(), 0, 0, 'C');
    }

    /** Convert UTF-8 to Windows-1252 for FPDF's built-in fonts. */
    private function enc(string $s): string
    {
        return mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
    }

    public function buildTable(array $cols, array $rows): void
    {
        if (!$cols) {
            return;
        }

        $pageW   = $this->GetPageWidth() - 2 * self::MARGIN;
        $n       = count($cols);
        $colW    = array_fill(0, $n, $pageW / $n);

        // Header row
        $this->SetFillColor(...self::MAROON);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetX(self::MARGIN);
        foreach ($cols as $i => $label) {
            $this->Cell($colW[$i], 7, $this->enc((string)$label), 0, 0, 'L', true);
        }
        $this->Ln();

        // Data rows
        $this->SetFont('Helvetica', '', 8);
        $odd = true;
        foreach ($rows as $row) {
            if ($this->GetY() > $this->GetPageHeight() - 20) {
                $this->AddPage();
            }
            $this->SetFillColor(...($odd ? self::GREY_BG : [255, 255, 255]));
            $this->SetTextColor(...self::TEXT);
            $this->SetX(self::MARGIN);
            foreach ($cols as $i => $_) {
                $cell = isset($row[$i]) ? (string)$row[$i] : '—';
                $this->Cell($colW[$i], 6, $this->enc($cell), 0, 0, 'L', true);
            }
            $this->Ln();
            $odd = !$odd;
        }

        // Bottom divider
        $this->SetDrawColor(...self::GREY_LN);
        $this->SetLineWidth(0.2);
        $this->Line(self::MARGIN, $this->GetY(), $this->GetPageWidth() - self::MARGIN, $this->GetY());
        $this->Ln(3);

        // Row count
        $this->SetFont('Helvetica', 'I', 7);
        $this->SetTextColor(120, 120, 120);
        $this->SetX(self::MARGIN);
        $this->Cell(0, 5, $this->enc(count($rows) . ' record(s)'), 0, 1, 'L');
    }
}

/**
 * Turn a rpt_generate() envelope into a PDF binary string.
 *
 * @param  array $data  ['title', 'date_range', 'cols', 'rows', 'generated_at']
 * @return string       raw PDF bytes
 */
/** Render multiple reports into one PDF, each starting on a new page. */
function rpt_render_pdf_multi(array $datasets): string
{
    if (count($datasets) === 1) {
        return rpt_render_pdf($datasets[0]);
    }

    $pdf = new ReportPdf('L', 'mm', 'A4');
    // Use the first dataset title as the cover; each section header overrides per page.
    $pdf->rptTitle = 'PUPSync — All Reports';
    $pdf->rptRange = $datasets[0]['date_range'] ?? '';
    $pdf->rptGenAt = $datasets[0]['generated_at'] ?? date('Y-m-d H:i:s');

    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetMargins(ReportPdf::MARGIN, 22, ReportPdf::MARGIN);

    foreach ($datasets as $data) {
        $pdf->rptTitle = $data['title'] ?? 'Report';
        $pdf->rptRange = $data['date_range'] ?? '';
        $pdf->rptGenAt = $data['generated_at'] ?? '';
        $pdf->AddPage();
        $pdf->buildTable($data['cols'] ?? [], $data['rows'] ?? []);
    }

    return $pdf->Output('S');
}

function rpt_render_pdf(array $data): string
{
    $pdf = new ReportPdf('L', 'mm', 'A4');
    $pdf->rptTitle = $data['title']        ?? 'Report';
    $pdf->rptRange = $data['date_range']   ?? '';
    $pdf->rptGenAt = $data['generated_at'] ?? '';

    $pdf->SetAutoPageBreak(true, 15);
    $pdf->SetMargins(ReportPdf::MARGIN, 22, ReportPdf::MARGIN);
    $pdf->AddPage();
    $pdf->buildTable($data['cols'] ?? [], $data['rows'] ?? []);

    return $pdf->Output('S');
}
