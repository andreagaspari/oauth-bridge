<?php
/**
 * Error 403 - Access Denied
 * Displays a user-friendly message when access to a resource is forbidden.
 * Uses the central layout `errors-layout.php`.
 * 
 * @since 0.1.01
 */
$statusCode = 403;
$title = '403 — Accesso negato';
$message = 'Non hai i permessi necessari per accedere a questa risorsa.';

require __DIR__ . '/errors-layout.php';

