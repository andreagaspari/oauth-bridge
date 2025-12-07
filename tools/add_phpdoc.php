<?php
// Simple PHPDoc inserter for classes and public/protected methods.
// Use with: php tools/add_phpdoc.php

$root = __DIR__ . '/../src';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$files = [];
foreach ($it as $f) {
    if ($f->isFile() && preg_match('/\\.php$/', $f->getFilename())) {
        $files[] = $f->getPathname();
    }
}

$modified = [];
foreach ($files as $file) {
    $fileContent = file_get_contents($file);
    $orig = $fileContent;

    // Skip files that declare no class
    if (!preg_match('/class\s+\w+/m', $fileContent)) {
        continue;
    }

    // Add class docblocks where missing (only first class match)
    $fileContent = preg_replace_callback('/(^\s*(?:abstract\s+|final\s+)?class\s+(\w+)[^\n\r]*\n)/m', function($m) use ($file) {
        $full = $m[0];
        $class = $m[2];
        $pos = strpos(file_get_contents($file), $full);
        if ($pos === false) return $full;
        $start = max(0, $pos - 400);
        $prev = substr(file_get_contents($file), $start, $pos - $start);
        if (preg_match('/\/\*\*/', $prev)) return $full; // already has a docblock nearby

        // try to infer namespace for @package
        $ns = '';
        if (preg_match('/namespace\s+([^;\\s]+(?:\\\\[^;\\s]+)*)\s*;/', file_get_contents($file), $n)) {
            $ns = trim($n[1]);
        }
            $package = $ns ? 'Immaginificio\\OAuthProxyBridge\\' . $ns : 'Immaginificio\\OAuthProxyBridge';

        $doc = "/**\n";
        $doc .= " * Class $class\n";
        $doc .= " *\n";
        $doc .= " * @package $package\n";
        $doc .= " * @since 0.0.1\n";
        $doc .= " */\n";

        return $doc . $full;

    }, $fileContent, 1);

    // Add method docblocks for public/protected functions lacking docblocks
    // capture optional return type (e.g. ): ?string or ): array
    $fileContent = preg_replace_callback('/(^\s*(public|protected)\s+function\s+(\w+)\s*\(([^\)]*)\)\s*(?:\:\s*([\\\w\|\?\[\]]+))?\s*\{?)/m', function($m) use ($file) {
        $sig = $m[0];
        $visibility = $m[2];
        $name = $m[3];
        $params = trim($m[4]);
        $retType = isset($m[5]) && $m[5] ? $m[5] : '';

        // find position and check for docblock in preceding 4 lines
        $fileContentLocal = file_get_contents($file);
        $pos = strpos($fileContentLocal, $sig);
        if ($pos === false) return $sig;
        $start = max(0, $pos - 300);
        $prev = substr($fileContentLocal, $start, $pos - $start);
        if (preg_match('/\/\*\*[\s\S]*?\*\//', $prev)) return $sig; // already has a docblock

        $doc = "/**\n";
        $doc .= " * $name\n";
        $doc .= " *\n";
        if ($params !== '') {
            // split params and try to extract types
            $parts = preg_split('/,\s*/', $params);
            foreach ($parts as $p) {
                $p = trim($p);
                if ($p === '') continue;
                // possible patterns: Type $name = default, ?Type $name, $name = default, Type &$name
                if (preg_match('/^(?:([\\\\\w\|\?\[\]]+)\s+)?(&?\$\w+)/', $p, $pm)) {
                    $type = isset($pm[1]) && $pm[1] ? $pm[1] : 'mixed';
                    $pname = $pm[2];
                } else {
                    // fallback
                    if (preg_match('/(\$\w+)/', $p, $pm2)) {
                        $pname = $pm2[1];
                        $type = 'mixed';
                    } else {
                        continue;
                    }
                }
                // normalize backslashes
                $type = str_replace('\\\\', '\\', $type);
                $doc .= " * @param $type $pname\n";
            }
        }
        $doc .= " * @return " . ($retType ?: 'mixed') . "\n";
        $doc .= " * @since 0.0.1\n";
        $doc .= " */\n";

        return $doc . $sig;
    }, $fileContent);

    if ($fileContent !== $orig) {
        // backup
        copy($file, $file . '.bak');
        file_put_contents($file, $fileContent);
        $modified[] = $file;
    }
}

// Output modified files
if (empty($modified)) {
    echo "No files modified\n";
    exit(0);
}

foreach ($modified as $m) echo "MODIFIED: $m\n";

exit(0);
