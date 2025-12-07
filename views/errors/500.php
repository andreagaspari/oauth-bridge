<?php
/**
 * Error 500 — Errore interno del server (standalone)
 */
http_response_code(500);
?>
<!doctype html>
<html lang="it">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>500 — Errore interno</title>
	<?php
	use Immaginificio\OAuthProxyBridge\Core\Assets;
	if (class_exists(Assets::class)) {
		Assets::enqueueStyle('palette','/assets/css/palette.css');
		Assets::enqueueComponents(['button','card','icon','grid','alert']);
		Assets::enqueueStyle('admin','/assets/css/admin.css');
		Assets::printStyles();
	}
	?>
</head>
<body>
	<div class="login-shell">
		<div class="login-card c-card" role="region" aria-label="Errore 500">
			<div class="c-card__header mb-2g">
				<h1 class="c-card__title">Errore interno del server</h1>
			</div>
			<div class="c-card__body">
				<p>Si è verificato un errore interno. Il problema è stato registrato.</p>
				<p>Riprova più tardi o contatta l'amministratore di sistema.</p>
				<p>
					<a class="c-btn c-btn--secondary" href="/admin">Torna alla dashboard</a>
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

