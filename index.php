<?php

/**
 * OAuth 2.0 Bridge - Front Controller
 *
 * Entry point dell'applicazione. Gestisce tutte le richieste HTTP,
 * inizializza il sistema tramite bootstrap.php e delega il flusso
 * al Router principale.
 *
 * @package   OAuthProxyBridge
 * @author    Andrea Gaspari
 * @license   GPL-3.0-or-later
 * @version   0.0.1
 */

require __DIR__ . '/bootstrap.php';

/** @var \Immaginificio\OAuthProxyBridge\Core\Router $router */
$router->dispatch();
