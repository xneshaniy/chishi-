<?php
$file = __DIR__ . '/../database/schema.mysql.sql';
$content = file_get_contents($file);

// Replace CREATE TABLE (not followed by IF NOT EXISTS) with CREATE TABLE IF NOT EXISTS
$newContent = preg_replace('/CREATE TABLE\s+(?!IF NOT EXISTS\s+)(`?[a-zA-Z0-9_]+`?)/i', 'CREATE TABLE IF NOT EXISTS $1', $content);

file_put_contents($file, $newContent);
echo "Updated schema.mysql.sql with CREATE TABLE IF NOT EXISTS!\n";
