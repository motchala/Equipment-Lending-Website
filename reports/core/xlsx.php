<?php

declare(strict_types=1);

/**
 * reports/core/xlsx.php
 *
 * Minimal XLSX renderer for the Generated Reports feature.
 * Builds a valid Office Open XML workbook using PHP's built-in ZipArchive.
 * No third-party library required.
 *
 * Input : the array returned by rpt_generate() in reports/core/data.php.
 * Output: an XLSX file as a binary string.
 *
 * No DB calls, no session access.
 */

/**
 * Escape a value for an XML cell.
 * Numbers are written as numeric cells; everything else as inline strings.
 */
function rpt_xlsx_cell(int $col, int $row, mixed $value): string
{
    $ref = rpt_xlsx_col_name($col) . $row;
    if (is_int($value) || is_float($value)) {
        return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
    }
    $s = htmlspecialchars((string)$value, ENT_XML1, 'UTF-8');
    return '<c r="' . $ref . '" t="inlineStr"><is><t>' . $s . '</t></is></c>';
}

function rpt_xlsx_col_name(int $n): string   // 0-indexed → A, B, ... Z, AA, ...
{
    $name = '';
    for ($n++; $n > 0; $n = intdiv($n - 1, 26)) {
        $name = chr(65 + (($n - 1) % 26)) . $name;
    }
    return $name;
}

/**
 * Turn a rpt_generate() envelope into an XLSX binary string.
 *
 * @param  array $data  ['title', 'date_range', 'cols', 'rows', 'generated_at']
 * @return string       raw XLSX bytes
 */
/** Render multiple reports into one XLSX workbook, one sheet per report. */
function rpt_render_xlsx_multi(array $datasets): string
{
    if (count($datasets) === 1) {
        return rpt_render_xlsx($datasets[0]);
    }

    $tmp = tempnam(sys_get_temp_dir(), 'rpt_');
    if ($tmp === false) {
        throw new RuntimeException('Could not create temp file for XLSX.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        unlink($tmp);
        throw new RuntimeException('Could not open ZipArchive for XLSX.');
    }

    $sheetXmls  = [];
    $sheetRels  = [];
    $sheetNames = [];
    $overrides  = '';

    foreach ($datasets as $i => $data) {
        $sheetNum  = $i + 1;
        $partName  = "/xl/worksheets/sheet{$sheetNum}.xml";
        $sheetXml  = rpt_build_sheet_xml($data);
        $zip->addFromString("xl/worksheets/sheet{$sheetNum}.xml", $sheetXml);

        // Safe sheet name: max 31 chars, no special chars
        $rawName = $data['title'] ?? 'Report';
        $safeName = substr(preg_replace('/[\/\\\?\*\[\]:]+/', '', $rawName), 0, 31);
        $sheetNames[] = htmlspecialchars($safeName, ENT_XML1, 'UTF-8');

        $overrides   .= '<Override PartName="' . $partName . '"'
            . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $sheetRels[] = '<Relationship Id="rId' . $sheetNum . '"'
            . ' Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet"'
            . ' Target="worksheets/sheet' . $sheetNum . '.xml"/>';
    }

    // [Content_Types].xml
    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml"  ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml"'
        . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . $overrides
        . '</Types>'
    );

    // _rels/.rels
    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>'
    );

    // xl/workbook.xml — one <sheet> per dataset
    $sheetsXml = '';
    foreach ($sheetNames as $i => $name) {
        $sheetsXml .= '<sheet name="' . $name . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
    }
    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets>' . $sheetsXml . '</sheets>'
        . '</workbook>'
    );

    // xl/_rels/workbook.xml.rels
    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . implode('', $sheetRels)
        . '</Relationships>'
    );

    $zip->close();
    $bytes = file_get_contents($tmp);
    unlink($tmp);

    if ($bytes === false) {
        throw new RuntimeException('Could not read generated XLSX file.');
    }

    return $bytes;
}

/** Build the inner sheet XML string for one dataset (shared by single and multi renderers). */
function rpt_build_sheet_xml(array $data): string
{
    $cols  = $data['cols']         ?? [];
    $rows  = $data['rows']         ?? [];
    $title = $data['title']        ?? 'Report';
    $range = $data['date_range']   ?? '';
    $genAt = $data['generated_at'] ?? '';

    $xmlRows = '';
    $rowIdx  = 1;

    $xmlRows .= '<row r="' . $rowIdx . '">';
    $xmlRows .= rpt_xlsx_cell(0, $rowIdx, $title . '  |  ' . $range . '  |  Generated: ' . $genAt);
    $xmlRows .= '</row>';
    $rowIdx++;

    $xmlRows .= '<row r="' . $rowIdx . '"></row>';
    $rowIdx++;

    if ($cols) {
        $xmlRows .= '<row r="' . $rowIdx . '">';
        foreach ($cols as $ci => $label) {
            $xmlRows .= rpt_xlsx_cell($ci, $rowIdx, (string)$label);
        }
        $xmlRows .= '</row>';
        $rowIdx++;
    }

    foreach ($rows as $row) {
        $xmlRows .= '<row r="' . $rowIdx . '">';
        foreach ($cols as $ci => $_) {
            $xmlRows .= rpt_xlsx_cell($ci, $rowIdx, $row[$ci] ?? '');
        }
        $xmlRows .= '</row>';
        $rowIdx++;
    }

    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . $xmlRows . '</sheetData>'
        . '</worksheet>';
}

function rpt_render_xlsx(array $data): string
{
    $sheetXml = rpt_build_sheet_xml($data);

    // ── Assemble the XLSX (ZIP) ───────────────────────────────────────────────
    $tmp = tempnam(sys_get_temp_dir(), 'rpt_');
    if ($tmp === false) {
        throw new RuntimeException('Could not create temp file for XLSX.');
    }

    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
        unlink($tmp);
        throw new RuntimeException('Could not open ZipArchive for XLSX.');
    }

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml"  ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml"'
        . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml"'
        . ' ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '</Types>'
    );

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>'
    );

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"'
        . ' xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>'
    );

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '</Relationships>'
    );

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->close();

    $bytes = file_get_contents($tmp);
    unlink($tmp);

    if ($bytes === false) {
        throw new RuntimeException('Could not read generated XLSX file.');
    }

    return $bytes;
}
