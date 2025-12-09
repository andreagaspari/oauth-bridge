<?php
/**
 * Errors Layout
 * 
 * Central layout for error pages.
 * Determines whether to respond with JSON (API) or HTML (browser) and returns
 * the appropriate content. Specific views should set some variables before
 * including this file:
 *  - $statusCode (int) HTTP status code
 *  - $title (string) brief title of the error
 *  - $message (string) readable message
 *  - $errors (mixed) optional, additional details
 *  - $html (string) optional, if the full HTML has already been built
 * 
 * @since 0.1.01
 */

$statusCode = isset($statusCode) ? (int) $statusCode : 500;
$title = isset($title) ? (string) $title : 'Errore';
$message = isset($message) ? (string) $message : 'Si \'\u00e8 verificato un errore.';
$errors = $errors ?? null;

/**
 * Check if the request is an API request.
 * This function checks various headers and the request URI to determine if
 * the request is intended for an API endpoint.
 * 
 * @return bool True if it's an API request, false otherwise.
 * @since 0.1.01
 */
function oauth_bridge_is_api_request(): bool
{
	if (php_sapi_name() === 'cli') {
		return false;
	}

	$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
	if (stripos($accept, 'application/json') !== false) {
		return true;
	}

	$contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
	if (stripos($contentType, 'application/json') !== false) {
		return true;
	}

	$xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
	if (strcasecmp($xhr, 'XMLHttpRequest') === 0) {
		return true;
	}

	$uri = $_SERVER['REQUEST_URI'] ?? '';
	if (preg_match('#/api(/|$)#i', $uri)) {
		return true;
	}

	return false;
}

// If it's an API request, return JSON response.
if (oauth_bridge_is_api_request()) {
	http_response_code($statusCode);
	header('Content-Type: application/json; charset=utf-8');

	$payload = [
		'status' => $statusCode,
		'title' => $title,
		'message' => $message,
	];

	if (!empty($errors)) {
		$payload['errors'] = $errors;
	}

	echo json_encode($payload, JSON_UNESCAPED_UNICODE);
	exit;
}

// If it's a web request, render the HTML error page.
http_response_code($statusCode);
if (isset($html) && is_string($html)) {
	echo $html;
	return;
}

// Default HTML template
?>
<!doctype html>
	<html lang="it">
		<head>
			<meta charset="utf-8">
			<meta name="viewport" content="width=device-width,initial-scale=1">
			<title><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></title>
			<?php
			use Immaginificio\OAuthProxyBridge\Core\Assets;
			if (class_exists(Assets::class)) {
				Assets::enqueueStyle('palette','/assets/css/palette.css');
				Assets::enqueueComponents(['button','card','icon','grid']);
				Assets::enqueueStyle('admin','/assets/css/admin.css');
				Assets::printStyles();
			}
			?>
		</head>
		<body>
			<div class="error-shell">
				<div class="error-card c-card" role="region" aria-label="Errore 404">
					<div class="c-card__header mb-2g">
						<h1 class="c-card__title"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></h1>
					</div>
					<div class="c-card__body">
						<p><?php echo nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')); ?></p>
						<p class="mt-3g text-center">
							<a class="c-btn c-btn--primary" href="/admin">Torna alla dashboard</a>
						</p>
					</div>
				</div>
			</div>

			<?php
			if (class_exists(Assets::class)) {
				Assets::printScripts(true);
			}
			?>
		</body>
	</html>
