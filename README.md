# OAuth Proxy Bridge

## Disclaimer dell'autore
Questo progetto è stato interamente **generato da modelli di intelligenza artificiale** (ChatGPT-5, GPT-5 mini e in piccola parte Claude Sonnet 4.5) a partire da specifiche richieste fornite dall'autore stesso. Una volta elaborata la struttura è stato generato il documento [README-dev.md](README-dev.md) contenente le linee guida usate durante lo sviluppo.<br/> 
Sebbene l'autore abbia supervisionato e revisionato il codice generato, **l'autore non si assume alcuna responsabilità per eventuali errori, vulnerabilità o problemi derivanti dall'uso di questo software**. Si consiglia vivamente di eseguire una revisione approfondita del codice e di testare accuratamente l'applicazione prima di utilizzarla in ambienti di produzione.


Questo progetto nasce dalla necessità di utilizzare un unico progetto Google OAuth per autorizzare utenti da più siti remoti (es. plugin WordPress o backend di siti web) per poter utilizzare una API privata (disponibile solo per progetti Google Cloud specifici, es. Google Business Profile API).

## Descrizione generale

In gnereale quest'applicazione è pensata per essere usata da siti remoti che vogliono autorizzare utenti verso provider esterni centralizzando le richieste verso il servizio OAuth in un'unica istanza. Il bridge funge da intermediario sicuro, gestendo i dettagli OAuth e proteggendo le chiavi API lato server. 
Inoltre permette di monitorare l'utilizzo da parte dei siti che usano il servizio, abilitare/disabilitare l'accesso, e rigenerare le chiavi in caso di compromissione.
I dati degli utenti finali (credenziali, token, ecc.) non sono memorizzati sul bridge ne registrati nei log, ma solo inoltrati in modo sicuro al sito remoto che ha richiesto l'autorizzazione.

## TODO:
- Inserire pagine di dettaglio dell'utilizzo del servizio da parte dei siti registrati (numero di chiamate, errori, ecc.)
- Aggiungere supporto per altri provider OAuth (Facebook, Microsoft, ecc.)

---

# Integration Guide (site-to-server)
Qui viene spiegato come i siti remoti (es. plugin WordPress o backend di un sito) devono integrare uno dei servizi OAuth disponibili attraverso questo Bridge Proxy per ottenere token OAuth dai provider esterni (per ora solo Google).

Da qui in avanti, il README si concentra sull'integrazione lato sito chiamante (es. WordPress).

Indice
- Panoramica
- Requisiti
- Endpoints principali
- Parametri (in input)
- Risposte (output)
- Flusso consigliato (server-proxy)
- Flusso alternativo (browser POST)
- Callback lato sito (es. WordPress)
- Refresh token
- Codici di errore comuni
- Esempi cURL
- Note di sicurezza

---

## Panoramica

Questo servizio centralizza il flusso OAuth verso provider esterni (per ora Google). I siti remoti possono chiedere al server di avviare l'autorizzazione per un utente, ricevere i token e gestire il rinnovo tramite l'endpoint di refresh.

Per motivi di sicurezza il server verifica che la chiamata provenga da un sito registrato e attivo tramite una `site_url` e una `api_key_server` (chiave API lato server generata e gestita tramite l'area admin del server).

## Requisiti
- Il sito remoto deve avere una `site_url` registrata nell'amministrazione del server e la corrispondente `api_key_server` (fornita dall'admin del servizio).
- Il sito remoto deve predisporre un endpoint che **riceva** i token post-OAuth dal server (il server effettua un POST auto-submit verso la callback configurata del sito). Nel caso dell'integrazione WordPress/Google (esempio qui), il server invia i token via POST a:

  `/wp-admin/admin-post.php?action=imm_google_business_profile_api_oauth_callback`

  (se il sito non è WordPress, predisporre un endpoint POST che accetti i campi descritti nella sezione "Callback lato sito").

## Endpoints principali

- Avvio autorizzazione (inizia il redirect verso il provider)
  - POST /auth/{provider}/start
  - Middleware: ApiKeyMiddleware (verifica `site` e `api_key_server`)
  - Esempio provider: `google`

- Callback provider
  - GET /callback
  - Pubblico: il provider reindirizza qui con `code` e `state`.
  - Il server completa lo scambio codice→token e poi inoltra i token al sito remoto via POST (auto-submit HTML form) per evitare che i token restino in query string.

- Refresh token
  - POST /auth/{provider}/refresh
  - Middleware: ApiKeyMiddleware (verifica `site` e `api_key_server`)
  - Scopo: scambiare un `refresh_token` per nuovi access token

## Parametri (input)

Per gli endpoint protetti da `ApiKeyMiddleware` è richiesto che la richiesta includa:
- `site` (string): URL base del sito come registrato (es. `https://example.it` o `https://example.it/dir`). Deve corrispondere esattamente (dopo normalizzazione `rtrim('/')`) a quanto registrato.
- `api_key_server` (string): chiave API server associata alla `site_url` registrata.

Endpoint specifici:
- POST /auth/{provider}/start
  - requisiti: `site`, `api_key_server`, provider nella path.
  - comportamento: valida site+key; genera `state` e risponde con un redirect verso l'URL di autorizzazione del provider (es. Google).

- GET /callback
  - parametri del provider: `code` (auth code), `state` (opzionale per CSRF)
  - comportamento: valida `state` memorizzato in sessione; scambia `code` per token; invia i token al sito remoto via POST (vedi sezione "Callback lato sito").

- POST /auth/{provider}/refresh
  - requisiti: `site`, `api_key_server`, `refresh_token` (nel body)
  - comportamento: valida site+key; chiama il provider per ottenere nuovi token e restituisce il JSON di risposta del provider.

> Nota: il middleware accetta `site` e `api_key_server` sia nel body POST che come parametri query/GET (il router definisce le rotte come POST), dunque la chiamata consigliata è POST lato server.

## Risposte (output)

- Avvio (`/auth/{provider}/start`): risposta HTTP 302 redirect verso l'URL del provider (Authorization URL). Non ritorna JSON.

- Callback (`/callback`): la pagina restituita è un HTML che esegue un `POST` auto-submit verso il sito remoto (campo hidden) contenente i seguenti campi:
  - `access_token` — token di accesso (string)
  - `refresh_token` — refresh token se fornito (string)
  - `_wpnonce` — valore nonce passato dalla sessione se presente (usato dall'integrazione WordPress nel codice attuale)

- Refresh (`/auth/{provider}/refresh`): restituisce JSON pari alla risposta del provider (es. Google), tipicamente:
  - `access_token` (string)
  - `expires_in` (int)
  - `scope` (string)
  - `token_type` (string)
  - eventualmente `refresh_token` (string) se fornito
  - oppure in caso di errore: `{ "error": "...", ... }`

## Flusso consigliato (RACCOMANDATO): proxy server-to-server

Per motivi di sicurezza è sconsigliato inserire `api_key_server` direttamente nella pagina HTML inviata al browser (perché esponendo il valore nel client si espone la chiave). Perciò raccomandiamo il seguente pattern:

1. L'utente clicca su un bottone "Connetti con Google" sul sito remoto.
2. Il sito remoto invia una richiesta server-to-server (back-end) verso `POST https://your-oauth-bridge.example/auth/google/start` con body form-encoded (`application/x-www-form-urlencoded`) o JSON contenente:
   - `site`: URL base del sito registrato
   - `api_key_server`: la chiave server (tenuta segreta nel back-end del sito remoto)
3. Il server OAuth risponderà con un redirect (HTTP 302) verso Google (Authorization URL). Il sito remoto **non** deve seguire il redirect lato server: deve leggere l'header `Location` della risposta ricevuta.
4. Il sito remoto risponde al browser con un redirect 302 verso quell'`Location` (o costruisce un link \<a\> e reindirizza il browser). In questo modo il `api_key_server` rimane solamente sul server remoto e non viene esposto al client.

Implementazione pratica (pseudo-curl server-side):

- Server remote (PHP esempio, non segue redirect):

```php
$ch = curl_init('https://oauth-bridge.example/auth/google/start');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['site'=>$site,'api_key_server'=>$apiKey]));
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$location = null;
if (preg_match('/^Location:\s*(.*)$/mi', $res, $m)) { $location = trim($m[1]); }
curl_close($ch);
if ($location) {
    // redirect browser to $location
    header('Location: ' . $location);
    exit;
}
```

Questo approccio mantiene la `api_key_server` segreta sul server remoto ed evita di includerla nel DOM della pagina inviata al browser.

## Flusso alternativo (meno raccomandato): POST diretto dal browser

Se si sceglie di inviare la richiesta `POST /auth/google/start` direttamente dal browser (form submit), allora il form deve contenere i campi `site` e `api_key_server`. Questo esporrà la chiave nel markup della pagina con i rischi noti: NON lo raccomandiamo per ambienti di produzione.

## Callback lato sito (esempio WordPress)

Il server, dopo aver scambiato `code` per token, esegue un auto-submit POST verso l'endpoint del sito remoto. Nell'implementazione corrente l'endpoint atteso è (WordPress):

```
/wp-admin/admin-post.php?action=imm_google_business_profile_api_oauth_callback
```

I campi POST inviati sono:
- `access_token`
- `refresh_token` (se presente)
- `_wpnonce` (opzionale, passato via sessione se configurato)

Il sito ricevente dovrebbe:
- Verificare il nonce (se previsto)
- Salvare i token in modo sicuro (server-side), associandoli all'account/site in questione
- Non esporre i token al client o loggarli

Se non usate WordPress, create un endpoint POST simile e adattate la ricezione dei campi.

## Refresh token

Endpoint: `POST /auth/{provider}/refresh`

Body (`application/x-www-form-urlencoded` o JSON):
- `site` — URL del sito
- `api_key_server` — chiave server segreta
- `refresh_token` — il refresh token ricevuto precedentemente

Risposta: JSON con i campi restituiti dal provider (es. `access_token`, `expires_in`, ...). In caso di errore la risposta può contenere `error`.

Esempio cURL:

```bash
curl -X POST https://oauth-bridge.example/auth/google/refresh \
  -d "site=https://example.it" \
  -d "api_key_server=YOUR_SECRET_KEY" \
  -d "refresh_token=REFRESH_TOKEN"
```

## Codici di errore comuni

- `400 Bad Request` — mancano parametri obbligatori (es. `site`, `api_key_server`, `code`, ...)
- `403 Forbidden` — `site`/`api_key_server` non validi (ApiKeyMiddleware fallito)
- `404 Not Found` — provider non supportato
- `500 Server Error` — errore interno (es. scambio token fallito)

Nel corpo JSON di errore (quando applicabile) viene inviato un oggetto con `error` e, se disponibile, `message`.

## Esempi pratici

1) Avvio (server-to-server proxy) — PHP (server remoto):

```php
$ch = curl_init('https://oauth-bridge.example/auth/google/start');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
  'site' => 'https://example.it',
  'api_key_server' => 'MY_SECRET_API_KEY'
]));
// non seguire il redirect automaticamente
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$location = null;
if (preg_match('/^Location:\s*(.*)$/mi', $res, $m)) { $location = trim($m[1]); }
curl_close($ch);
if ($location) {
  header('Location: ' . $location);
  exit;
}
// altrimenti gestire l'errore
```

2) Refresh token (server-to-server):

```bash
curl -X POST https://oauth-bridge.example/auth/google/refresh \
  -d "site=https://example.it" \
  -d "api_key_server=MY_SECRET_API_KEY" \
  -d "refresh_token=LONG_REFRESH_TOKEN"
```

Risposta tipica (Google):

```json
{
  "access_token": "ya29.a...",
  "expires_in": 3599,
  "scope": "openid https://www.googleapis.com/auth/business.manage",
  "token_type": "Bearer"
}
```

## Google-specific notes

- Lo scope di default usato dal server Google è `openid email profile` (configurabile nel service config sul server). Per Business/Profile potrebbero essere necessari scope aggiuntivi (es. `https://www.googleapis.com/auth/business.manage`).
- Il server chiede `access_type=offline` e `prompt=consent` per ottenere refresh token.
- I client dovranno conservare in modo sicuro il `refresh_token` per richiedere nuovi `access_token` quando scadono.

## Best practices e sicurezza

- Non esporre `api_key_server` nel markup delle pagine pubbliche. Usare lo schema *proxy* server-to-server per generare il redirect al provider.
- Conservare token (soprattutto `refresh_token`) in storage server-side cifrato o in DB accessibile solo dalla parte server dell'applicazione.
- Limitare i privilegi della `api_key_server` e rigenerarla in caso di compromissione.
- Monitorare i log del server per `invalid_provider` o `invalid_api_key` e reagire a tentativi sospetti.

---

Se vuoi, posso:
- aggiungere esempi lato WordPress (snippet PHP per ricevere il POST callback),
- documentare altri provider (Facebook, Microsoft) appena disponibili,
- aggiungere diagrammi di sequence (testuale) per chiarire il flusso.

Documentazione generata il: 7 dicembre 2025
