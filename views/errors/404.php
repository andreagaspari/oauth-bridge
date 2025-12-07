<?php
/**
 * Error 404 — Pagina non trovata (standalone)
 */
http_response_code(404);
?>
<!doctype html>
<html lang="it">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width,initial-scale=1">
	<title>404 — Pagina non trovata</title>
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
	<div class="login-shell">
		<div class="login-card c-card" role="region" aria-label="Errore 404">
			<div class="c-card__header mb-2g">
				<h1 class="c-card__title">Pagina non trovata</h1>
			</div>
			<div class="c-card__body">
				<p>La risorsa richiesta non esiste o è stata spostata.</p>
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

