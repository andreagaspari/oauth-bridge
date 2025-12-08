<?php
/**
 * OAuth 2.0 Bridge - Front Controller
 *
 * Application entry point. Handles all HTTP requests,
 * initializes the system via bootstrap.php, and delegates the flow
 * to the main Router.
 *
 * @package   OAuthProxyBridge
 * @author    Andrea Gaspari
 * @license   GPL-3.0-or-later
 * @version   0.1.0
 */

require __DIR__ . '/bootstrap.php';

$router->dispatch();
