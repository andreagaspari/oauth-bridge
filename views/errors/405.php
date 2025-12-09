<?php
/**
 * Error 405 - Method Not Allowed
 * Shows a user-friendly message when the HTTP method used is not allowed for the requested resource.
 * Uses the central layout `errors-layout.php`.
 * 
 * @since 0.1.01
 */
$statusCode = 405;
$title = '405 — Metodo non consentito';
$message = 'Il metodo HTTP utilizzato non è consentito per questa risorsa.';

require __DIR__ . '/errors-layout.php';

