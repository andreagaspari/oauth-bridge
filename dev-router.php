<?php
// Simple router for PHP built-in webserver
// Usage: php -S 127.0.0.1:8000 dev-router.php

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// serve file directly if it exists
$file = __DIR__ . $uri;
if ($uri !== '/' && file_exists($file) && is_file($file)) {
    return false; // let the webserver serve the file
}

// otherwise forward to front controller
require __DIR__ . '/index.php';
