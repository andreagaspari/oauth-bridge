<?php
/**
 * Configurazione dei provider OAuth esterni
 * Restituisce un array associativo con le impostazioni per ciascun provider.
 * I valori di default vengono letti dalle variabili d'ambiente per permettere
 * la gestione centralizzata tramite `.env`.
 *
 * @since 0.0.1
 * @author Immaginificio
 * @license GNU GPL v3
 * @package OAuthProxyBridge
 */

return [
	'google' => [
		'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? (defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : ''),
		'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? (defined('GOOGLE_CLIENT_SECRET') ? GOOGLE_CLIENT_SECRET : ''),
		'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? (defined('GOOGLE_REDIRECT_URI') ? GOOGLE_REDIRECT_URI : ''),
	],
	// Aggiungere altri provider qui sotto, ad es. 'microsoft' => [...]
];
