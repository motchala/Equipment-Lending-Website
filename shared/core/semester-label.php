<?php

/**
 * semester-label.php
 *
 * Read-only helper: "which semester are we in right now?"
 *
 * The Super Admin picks the current semester (1st Semester / 2nd Semester / Summer / None) in
 * Settings -> Arbitration -> "Current semester". This file only READS that choice through
 * ArbitrationConfig; it never writes anything. Only the NAME is returned - never the dates - so a
 * view cannot show them by accident.
 *
 * USAGE (any portal):
 *   require_once __DIR__ . '/../../shared/core/semester-label.php';   // path from the including file
 *   $sem = ps_semester_label($conn);   // $conn is optional; the helper opens its own if omitted
 *   // $sem['label']  -> "1st Semester" | "2nd Semester" | "Summer" | "No active semester"
 *   // $sem['active'] -> false only when the Super Admin chose None (or nothing is set yet)
 */

require_once __DIR__ . '/arbitration-config.php';

if (!function_exists('ps_semester_label')) {
    /**
     * @param mysqli|null $conn Existing connection (admin / faculty pages). Omit on pages that have none.
     * @return array{label:string, active:bool}
     */
    function ps_semester_label(?mysqli $conn = null): array
    {
        $none = ['label' => ArbitrationConfig::SEMESTER_LABELS['none'], 'active' => false];

        try {
            if ($conn === null) {
                require_once __DIR__ . '/../../config/db.php';
                $conn = getDB();
            }
            $key = (string)(ArbitrationConfig::all($conn)['semester_name'] ?? 'none');
            if ($key === 'none' || !isset(ArbitrationConfig::SEMESTER_LABELS[$key])) {
                return $none;
            }
            return ['label' => ArbitrationConfig::SEMESTER_LABELS[$key], 'active' => true];
        } catch (Throwable $e) {
            // A missing label must never break a dashboard.
            error_log('[semester-label] ' . $e->getMessage());
            return $none;
        }
    }
}
