<?php
/**
 * Error 500 — Errore interno del server
 * Usa il layout centrale `errors-layout.php`.
 */
$statusCode = 500;
$title = '500 — Errore interno';
$message = "Si è verificato un errore interno. Il problema è stato registrato.";

require __DIR__ . '/errors-layout.php';

