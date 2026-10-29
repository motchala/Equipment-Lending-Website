<?php

declare(strict_types=1);

/**
 * Read faculty rows from the first worksheet in an XLSX workbook.
 *
 * Supports the Faculty ID / Professor Name / Programs Handled columns, either
 * in that order or identified by their header names. Files are read in place;
 * archive entries are never extracted to disk.
 */
function faculty_registry_parse_xlsx(string $path): array
{
    if (!class_exists(ZipArchive::class) || !class_exists(DOMDocument::class)) {
        throw new RuntimeException('The server is missing the XLSX reader. Please contact the server administrator.');
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) {
        throw new RuntimeException('The XLSX file could not be opened. Please choose a valid workbook.');
    }

    try {
        $workbook = faculty_registry_xlsx_xml($zip, 'xl/workbook.xml');
        $workbookPath = faculty_registry_xlsx_xml_document($workbook);
        $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $officeRelNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $packageRelNs = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $sheets = $workbookPath->getElementsByTagNameNS($mainNs, 'sheet');
        if ($sheets->length === 0) {
            throw new RuntimeException('The XLSX workbook does not contain a worksheet.');
        }

        $firstSheet = $sheets->item(0);
        if (!$firstSheet instanceof DOMElement) {
            throw new RuntimeException('The first worksheet could not be found in this XLSX workbook.');
        }
        $relationshipId = $firstSheet->getAttributeNS($officeRelNs, 'id');
        if ($relationshipId === '') {
            throw new RuntimeException('The first worksheet could not be found in this XLSX workbook.');
        }

        $relationships = faculty_registry_xlsx_xml_document(
            faculty_registry_xlsx_xml($zip, 'xl/_rels/workbook.xml.rels')
        );
        $relationshipNodes = $relationships->getElementsByTagNameNS($packageRelNs, 'Relationship');
        $target = '';
        foreach ($relationshipNodes as $relationship) {
            if (!$relationship instanceof DOMElement) {
                continue;
            }
            if ($relationship->getAttribute('Id') === $relationshipId) {
                $target = $relationship->getAttribute('Target');
                break;
            }
        }
        if ($target === '') {
            throw new RuntimeException('The first worksheet could not be found in this XLSX workbook.');
        }

        $worksheetPath = faculty_registry_xlsx_resolve_path($target);
        if ($worksheetPath === null) {
            throw new RuntimeException('The first worksheet path in this XLSX workbook is invalid.');
        }
        $worksheet = faculty_registry_xlsx_xml_document(faculty_registry_xlsx_xml($zip, $worksheetPath));

        $sharedStrings = [];
        if ($zip->locateName('xl/sharedStrings.xml') !== false) {
            $stringsDocument = faculty_registry_xlsx_xml_document(
                faculty_registry_xlsx_xml($zip, 'xl/sharedStrings.xml')
            );
            foreach ($stringsDocument->getElementsByTagNameNS($mainNs, 'si') as $stringNode) {
                if (!$stringNode instanceof DOMElement) {
                    continue;
                }
                $text = '';
                foreach ($stringNode->getElementsByTagNameNS($mainNs, 't') as $textNode) {
                    $text .= $textNode->textContent;
                }
                $sharedStrings[] = $text;
            }
        }

        $matrix = [];
        foreach ($worksheet->getElementsByTagNameNS($mainNs, 'row') as $rowNode) {
            if (!$rowNode instanceof DOMElement) {
                continue;
            }
            $row = [];
            foreach ($rowNode->getElementsByTagNameNS($mainNs, 'c') as $cellNode) {
                if (!$cellNode instanceof DOMElement) {
                    continue;
                }
                $reference = strtoupper($cellNode->getAttribute('r'));
                if (!preg_match('/^([A-Z]+)/', $reference, $match)) {
                    continue;
                }
                $column = faculty_registry_xlsx_column_index($match[1]);
                $valueNode = $cellNode->getElementsByTagNameNS($mainNs, 'v')->item(0);
                $value = $valueNode ? $valueNode->textContent : '';
                $type = $cellNode->getAttribute('t');

                if ($type === 's') {
                    $value = $sharedStrings[(int)$value] ?? '';
                } elseif ($type === 'inlineStr') {
                    $value = '';
                    foreach ($cellNode->getElementsByTagNameNS($mainNs, 't') as $textNode) {
                        $value .= $textNode->textContent;
                    }
                }
                $row[$column] = trim((string)$value);
            }
            if ($row) {
                $matrix[] = $row;
            }
        }

        if (!$matrix) {
            throw new RuntimeException('No rows were found in the first worksheet.');
        }

        $idColumn = 0;
        $nameColumn = 1;
        $departmentColumn = 2;
        $firstDataRow = 0;
        foreach ($matrix[0] as $column => $value) {
            $header = strtolower((string)preg_replace('/[^a-z0-9]/i', '', $value));
            if (in_array($header, ['facultyid', 'id', 'facultyidentificationnumber'], true)) {
                $idColumn = $column;
            } elseif (in_array($header, ['professorname', 'fullname', 'name'], true)) {
                $nameColumn = $column;
            } elseif (in_array($header, ['programshandled', 'department', 'program'], true)) {
                $departmentColumn = $column;
            }
        }
        $headerHasId = false;
        $headerHasName = false;
        foreach ($matrix[0] as $value) {
            $header = strtolower((string)preg_replace('/[^a-z0-9]/i', '', $value));
            $headerHasId = $headerHasId || in_array($header, ['facultyid', 'id', 'facultyidentificationnumber'], true);
            $headerHasName = $headerHasName || in_array($header, ['professorname', 'fullname', 'name'], true);
        }
        if ($headerHasId && $headerHasName) {
            $firstDataRow = 1;
        } else {
            $idColumn = 0;
            $nameColumn = 1;
            $departmentColumn = 2;
        }

        $rows = [];
        for ($i = $firstDataRow, $count = count($matrix); $i < $count; $i++) {
            $row = $matrix[$i];
            $name = trim((string)($row[$nameColumn] ?? ''));
            if ($name === '') {
                continue;
            }
            $facultyId = strtoupper(trim((string)($row[$idColumn] ?? '')));
            if (preg_replace('/\s+/', ' ', $facultyId) === 'NEWLY HIRED') {
                $facultyId = 'NEWLY HIRED';
            } else {
                $facultyId = (string)preg_replace('/\s+/', '', $facultyId);
            }
            $rows[] = [
                'faculty_id' => $facultyId,
                'full_name' => $name,
                'department' => trim((string)($row[$departmentColumn] ?? '')),
            ];
            if (count($rows) > 5000) {
                throw new RuntimeException('This workbook has more than 5,000 faculty rows. Split it into smaller files.');
            }
        }

        if (!$rows) {
            throw new RuntimeException('No faculty rows were found. Check that the first worksheet has Faculty ID and Professor Name columns.');
        }

        return $rows;
    } finally {
        $zip->close();
    }
}

function faculty_registry_xlsx_xml(ZipArchive $zip, string $entry): string
{
    $index = $zip->locateName($entry);
    if ($index === false) {
        throw new RuntimeException('The XLSX workbook is missing required worksheet data.');
    }
    $stat = $zip->statIndex($index);
    if (!is_array($stat) || (int)($stat['size'] ?? 0) > 20 * 1024 * 1024) {
        throw new RuntimeException('The XLSX worksheet data is too large to process.');
    }
    $xml = $zip->getFromIndex($index);
    if (!is_string($xml)) {
        throw new RuntimeException('The XLSX worksheet data could not be read.');
    }
    return $xml;
}

function faculty_registry_xlsx_xml_document(string $xml): DOMDocument
{
    if (stripos($xml, '<!DOCTYPE') !== false) {
        throw new RuntimeException('The XLSX file contains invalid worksheet data.');
    }

    $document = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);
    if (!$loaded) {
        throw new RuntimeException('The XLSX file contains invalid worksheet data.');
    }
    return $document;
}

function faculty_registry_xlsx_resolve_path(string $target): ?string
{
    $parts = [];
    $path = str_starts_with($target, '/') ? substr($target, 1) : 'xl/' . $target;
    foreach (explode('/', $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            if (!$parts) {
                return null;
            }
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }
    $resolved = implode('/', $parts);
    return preg_match('#^xl/worksheets/[^/]+\.xml$#', $resolved) ? $resolved : null;
}

function faculty_registry_xlsx_column_index(string $letters): int
{
    $index = 0;
    for ($i = 0, $length = strlen($letters); $i < $length; $i++) {
        $index = $index * 26 + ord($letters[$i]) - ord('A') + 1;
    }
    return $index - 1;
}
