<?php
$db = new PDO('sqlite:' . __DIR__ . '/../storage/dev.sqlite');

// Get all sqlite_master entries for tables and indexes
$stmt = $db->query("SELECT type, name, sql FROM sqlite_master WHERE type IN ('table', 'index') AND name NOT LIKE 'sqlite_%' ORDER BY type DESC, name ASC");
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

$schemaSql = "-- ==========================================================\n";
$schemaSql .= "-- Chishti Tobacco Company (CTC) ERP — Database Schema (SQLite)\n";
$schemaSql .= "-- Auto-generated full schema for Production & Deployment\n";
$schemaSql .= "-- ==========================================================\n\n";

foreach ($entries as $row) {
    if ($row['sql']) {
        $schemaSql .= $row['sql'] . ";\n\n";
    }
}

file_put_contents(__DIR__ . '/../database/schema.sqlite.sql', $schemaSql);
echo "Updated database/schema.sqlite.sql successfully with " . count($entries) . " items!\n";
