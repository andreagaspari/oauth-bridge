<?php
/**
 * Error 404 - Page Not Found
 * Shows a user-friendly message when a requested resource is not found.
 * Uses the central layout `errors-layout.php`.
 * 
 * @since 0.1.01
 */
$statusCode = 404;
$title = '404 — Pagina non trovata';
$message = 'La risorsa richiesta non esiste o è stata spostata.';

require __DIR__ . '/errors-layout.php';

