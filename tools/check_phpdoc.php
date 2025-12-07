<?php
// Scans `src/` for classes and public/protected methods missing a PHPDoc block.
// Usage: php tools/check_phpdoc.php

$root = __DIR__ . '/../src';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$files = [];
foreach ($it as $f) {
    if ($f->isFile() && preg_match('/\.php$/', $f->getFilename())) {
        $files[] = $f->getPathname();
    }
}

$issues = [];
foreach ($files as $file) {
    $content = file_get_contents($file);
    // normalize line endings
    $content = str_replace(["\r\n", "\r"], "\n", $content);

    // check for class docblocks
    if (preg_match_all('/(^\s*(?:abstract\s+|final\s+)?class\s+(\w+)[^\n]*\n)/m', $content, $classes, PREG_SET_ORDER)) {
        foreach ($classes as $c) {
            $full = $c[0];
            $name = $c[2];
            $pos = strpos($content, $full);
            if ($pos === false) continue;
            $prev = substr($content, max(0, $pos - 400), $pos - max(0, $pos - 400));
            if (!preg_match('/\/\*\*/', $prev)) {
                $line = substr_count(substr($content, 0, $pos), "\n") + 1;
                $issues[] = [
                    'file' => $file,
                    'line' => $line,
                    'type' => 'class',
                    'name' => $name,
                ];
            }
        }
    }

    // check for methods without docblock
    if (preg_match_all('/(^\s*(public|protected)\s+function\s+(\w+)\s*\([^\)]*\)\s*(?:\:\s*[\\\w\|\?\[\]]+)?\s*\{?)/m', $content, $methods, PREG_SET_ORDER)) {
        foreach ($methods as $m) {
            $sig = $m[0];
            $name = $m[3];
            $pos = strpos($content, $sig);
            if ($pos === false) continue;
            $prev = substr($content, max(0, $pos - 300), $pos - max(0, $pos - 300));
            if (!preg_match('/\/\*\*[\s\S]*?\*\//', $prev)) {
                $line = substr_count(substr($content, 0, $pos), "\n") + 1;
                $issues[] = [
                    'file' => $file,
                    'line' => $line,
                    'type' => 'method',
                    'name' => $name,
                ];
            }
        }
    }
}

if (empty($issues)) {
    echo "No missing DocBlocks detected in src/\n";
    exit(0);
}

// Group by file and print
$grouped = [];
foreach ($issues as $it) {
    $grouped[$it['file']][] = $it;
}

foreach ($grouped as $file => $items) {
    echo "File: $file\n";
    foreach ($items as $it) {
        printf("  [%s:%d] Missing DocBlock for %s '%s'\n", basename($file), $it['line'], $it['type'], $it['name']);
    }
    echo "\n";
}

exit(0);
