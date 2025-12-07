# OAuthProxyBridge – Documentazione Tecnica Completa

Benvenuto in `OAuthProxyBridge` (progetto short-name: `oauth-bridge`), un bridge modulare e sicuro per la gestione di flussi OAuth 2.0 tra provider esterni (Google, Meta, Apple, LinkedIn, ecc.) e applicazioni client come WordPress.

Il progetto è organizzato come un **micro–framework PHP strutturato**, con routing, middleware, controller, servizi e modelli, compatibile con hosting condivisi tramite `.htaccess`.

Questo README contiene:
- Descrizione dell’architettura
- Struttura completa delle cartelle
- Ruolo dei file principali
- Linee guida di sviluppo
- Convenzioni per la documentazione interna (`DocBlock`)
- Standard per versionamento tramite `@since`
- Note di sicurezza

---

# 🚀 Obiettivo del progetto

L'OAuthProxyBridge funge da **ponte (bridge)** tra siti/webapp esterne e provider di autenticazione OAuth2.  
Il Bridge:

- Avvia il flusso OAuth (`start`)
- Gestisce il callback che restituisce `code` e `state`
- Esegue il token exchange (access + refresh token)
- Permette il `refresh` dei token
- Non conserva token sensibili (stateless bridge)
- Valida i siti tramite API key
- Offre un pannello admin per gestione utenti / chiavi / log
- Supporta configurazioni multi-tenant: più siti possono accedere con chiavi distinte.

---

# ⚙️ Installazione (server)

Questa sezione spiega come installare e mettere in produzione `oauth-bridge` (alias `OAuthProxyBridge`) su un server Linux/Apache (o ambiente simile). Le istruzioni sono intenzionalmente conservative: preferisci sempre un ambiente di staging prima della produzione.

Prerequisiti minimi
- PHP 8.0+ con estensioni: `pdo`, `pdo_mysql`, `curl`, `mbstring`, `json`, `openssl`.
- Composer (per autoload e dipendenze opzionali)
- MySQL/MariaDB (o altro DB supportato da PDO) se vuoi usare il DB per `site_keys`, `users` e `logs`.
- Apache con `mod_rewrite` (o nginx con configurazione equivalente)
- HTTPS con certificato valido (Let’s Encrypt consigliato)

Passi di installazione

1. Checkout del repository

```bash
git clone https://github.com/andreagaspari/oauth-bridge.git oauth-bridge
cd oauth-bridge
```

2. Dipendenze PHP

```bash
composer install --no-dev --optimize-autoloader
```

3. Configurazione ambiente
- Copia il file di esempio `.env.example` (se presente) in `.env` e modifica i valori:
	- `DB_DSN`, `DB_USER`, `DB_PASS` — dati per la connessione al DB
	- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` — credenziali Google
	- `APP_ENV=production` e `APP_DEBUG=0` in produzione

Esempio minimo `.env`:

```
DB_DSN=mysql:host=127.0.0.1;dbname=oauth_bridge;charset=utf8mb4
DB_USER=oauth_user
DB_PASS=supersecret

GOOGLE_CLIENT_ID=xxxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=yyyy
GOOGLE_REDIRECT_URI=https://your-oauth-bridge.example/callback

APP_ENV=production
APP_DEBUG=0
```

4. Creare il database e applicare schema

- Creare il DB e l'utente, quindi importare `config/schema.sql`:

```sql
CREATE DATABASE oauth_bridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'oauth_user'@'localhost' IDENTIFIED BY 'supersecret';
GRANT ALL PRIVILEGES ON oauth_bridge.* TO 'oauth_user'@'localhost';
```

Quindi importare lo schema:

```bash
mysql -u oauth_user -p oauth_bridge < config/schema.sql
```

5. Permessi e proprietà

Assicurati che il webserver possa leggere il codice e scrivere le cartelle necessarie (se usi caching / upload). Esempio con Apache su Ubuntu:

```bash
sudo chown -R www-data:www-data /var/www/oauth-bridge
sudo find /var/www/oauth-bridge -type d -exec chmod 755 {} \;
sudo find /var/www/oauth-bridge -type f -exec chmod 644 {} \;
```

6. Configurare il VirtualHost (Apache)

Esempio minimo di VirtualHost:

```apache
<VirtualHost *:80>
		ServerName your-oauth-bridge.example
		DocumentRoot /var/www/oauth-bridge

		<Directory /var/www/oauth-bridge>
				AllowOverride All
				Require all granted
		</Directory>

		ErrorLog ${APACHE_LOG_DIR}/oauth-bridge_error.log
		CustomLog ${APACHE_LOG_DIR}/oauth-bridge_access.log combined
</VirtualHost>
```

Abilita `mod_rewrite` e riavvia Apache:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

7. HTTPS

Attiva HTTPS (Let’s Encrypt / Certbot consigliato):

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d your-oauth-bridge.example
```

8. Configurazioni aggiuntive
- Imposta cron o job di monitoraggio se vuoi pulire log o eseguire task periodici.
- Controlla `config/providers.php` per personalizzare i redirect URI dei provider.

9. Avvio in locale per sviluppo

Per test rapido in ambiente di sviluppo puoi usare il server integrato di PHP:

```bash
php -S 127.0.0.1:8000 dev-router.php
```

Verifiche post-installazione
- Visita `https://your-oauth-bridge.example/ping` (dovrebbe rispondere `OK`).
- Accedi all'area admin (`/admin`) e crea una `site_key` per il sito client.
- Testa il flusso Google con un sito client in staging (usa il proxy server-to-server consigliato).

Note di produzione
- Non salvare le chiavi `api_key_server` nel frontend. Conserva in secure storage lato server.
- Abilita logging sicuro e rotazione log.
- Limita le origini consentite (CORS) se esponi API pubbliche.

---

# 📁 Architettura del progetto

```
/
├─ assets/
│   ├─ css/
│   └─ js/
│
├─ config/
│   ├─ config.php            → configurazioni generali
│   ├─ db.php                → connessione database
│   ├─ providers.php         → definizione provider OAuth
│   └─ schema.sql            → script di installazione DB
│
├─ src/
│   ├─ Controllers/
│   │    ├─ OAuthController.php
│   │    ├─ AdminController.php
│   │    └─ ApiController.php
│   │
│   ├─ Core/
│   │    ├─ Router.php
│   │    ├─ Request.php
│   │    ├─ Response.php
│   │    ├─ Session.php
│   │    └─ Auth.php
│   │
│   ├─ Middleware/
│   │    ├─ MiddlewareInterface.php
│   │    ├─ ApiKeyMiddleware.php
│   │    ├─ AuthMiddleware.php
│   │    ├─ CsrfMiddleware.php
│   │    ├─ LogMiddleware.php
│   │    └─ RateLimitMiddleware.php
│   │
│   ├─ Models/
│   │    ├─ SiteKey.php
│   │    ├─ User.php
│   │    └─ Log.php
│   │
│   └─ Services/
│        ├─ ServiceInterface.php
│        ├─ ServiceManager.php
│        └─ GoogleService.php
│
├─ views/
│   ├─ admin/
│   └─ errors/
│
├─ index.php                 → front controller
├─ bootstrap.php             → inizializzazione framework
├─ routes.php                → definizione rotte
│
├─ .env                      → variabili d’ambiente
├─ .htaccess                 → routing + protezioni
├─ composer.json             → autoload e dipendenze
└─ .gitignore
```

---

# 📌 Ruolo dei file principali

## index.php (front controller)
- Punto di ingresso dell’intera applicazione.
- Carica `bootstrap.php`.
- Avvia il router.
- Gestisce eventuali eccezioni.
- Non contiene logica applicativa.

## bootstrap.php
Inizializza:
- Autoload Composer
- Variabili `.env`
- Sessioni
- Router
- Middleware globali
- Caricamento di `routes.php`

## routes.php
Mappa URL → controller + middleware, esempio:

```
POST /auth/{provider}/start    → OAuthController::start
GET  /callback                 → OAuthController::callback
POST /auth/{provider}/refresh  → OAuthController::refresh

/admin/*                       → AdminController + AuthMiddleware
```

## .htaccess
- Protezione directory sensibili: `src/`, `config/`, `vendor/`, `.env`
- Routing single-entry-point verso `index.php`

## src/Core
Mini-framework interno:
- Router custom con parametri (es. `{provider}`)
- Request, Response
- Session handler sicuro
- Auth (per pannello admin)

## src/Middleware
Eseguono controlli prima del controller:
- Validazione API key
- Verifica login admin
- Rate limiting
- Logging richieste

## src/Services
Implementano provider OAuth:
- `ServiceInterface.php`
- `ServiceManager.php` (factory)
- `GoogleService.php` (primo provider implementato)

## src/Models
Entità persistenti:
- `User` (admin)
- `SiteKey` (API key e siti autorizzati)
- `Log` (registro richieste)

---

# 📚 Convenzioni per la documentazione (DocBlock)

L’intero progetto segue la regola:

> **Ogni file, classe, metodo e funzione deve contenere un DocBlock completo**, inclusi i tag:
> - `@package`
> - `@author`
> - `@since`
> - `@param`
> - `@return`

### Esempio DOC per file

```php
/**
 * Gestisce il routing principale dell'applicazione.
 *
 * @package OAuthProxyBridge\Core
 * @since 0.0.1
 * @author ...
 */
```

### Esempio DOC per classe

```php
/**
 * Router HTTP custom con supporto middleware e parametri dinamici.
 *
 * @since 0.0.1
 */
class Router { ... }
```

### Esempio DOC per metodo

```php
/**
 * Registra una nuova rotta GET.
 *
 * @param string   $path
 * @param callable $handler
 * @param array    $middleware
 * @return self
 *
 * @since 0.0.01
 */
public function get($path, $handler, array $middleware = []) { ... }
```

---

# 🧩 Gestione Componenti e Asset

Per mantenere le view leggere e coerenti abbiamo introdotto un piccolo asset manager PHP in `src/Core/Assets.php` con helper per registrare file CSS/JS dei componenti.

Principali API disponibili:

- `Assets::enqueueStyle(string $handle, string $path)` — enqueue uno stylesheet con handle personalizzato.
- `Assets::enqueueScript(string $handle, string $path, array $deps = [], bool $inFooter = true)` — enqueue uno script.
- `Assets::enqueueComponentStyle(string|array $component, ?string $baseDir = null)` — enqueue il CSS di un componente usando la convenzione `/assets/css/components/{name}.css`.
- `Assets::enqueueComponentScript(string|array $component, ?string $baseDir = null, array $deps = [], bool $inFooter = true)` — enqueue lo script del componente in `/assets/js/components/{name}.js`.
- `Assets::enqueueComponents(array $components, ?string $cssBase = null, ?string $jsBase = null, array $defaultDeps = [], bool $defaultInFooter = true)` — enqueue in batch una lista di componenti; ogni elemento può essere una stringa (es. `'button'`) o un array di configurazione (es. `['name'=>'modal','css'=>true,'js'=>true,'deps'=>[], 'in_footer'=>true]`).

Esempi rapidi da inserire nelle view (prima del `require 'layout.php'`):

```php
use Immaginificio\OAuthProxyBridge\Core\Assets;

// enqueue + stampare (layout chiamerà printStyles()/printScripts())
Assets::enqueueComponentStyle('button');
Assets::enqueueComponentScript('toggle-password');

// batch: enqueues css+js convenzionali per i nomi indicati (i nomi vengono risolti sotto
// `/assets/css/components/{name}.css` e `/assets/js/components/{name}.js`)
Assets::enqueueComponents(['button','icon-button','card','field','input-group','grid','icon']);

// NOTE: per script non presenti nella cartella `components/` (es. `assets/js/admin-login.js`)
// passare il percorso esplicito oppure usare `Assets::enqueueScript()`:
Assets::enqueueScript('admin-login','/assets/js/admin-login.js', [], true);
```

Note:
- Le view non devono stampare manualmente i tag `<link>` / `<script>` quando usano `Assets` — il layout (`views/admin/layout.php`) chiama `Assets::printStyles()` in head e `Assets::printScripts()` in fondo.
- `Assets` aggiunge automaticamente un `?v=<mtime>` ai percorsi locali per il cache busting.
- `enqueueComponents()` assume che i nomi forniti siano componenti sotto la cartella `components/`. Se passi invece un percorso completo (es. `'/assets/js/admin-login.js'`) verrà usato così com'è.
- Se la classe `Assets` non è disponibile (es. durante fasi di bootstrap), le view mantengono un fallback che include i file staticamente.

Grid note:
- È presente il CSS componente `assets/css/components/grid.css` per lo stile delle griglie responsivi. Il trasformatore JS che convertiva tabelle in grid è stato rimosso: le pagine admin ora generano direttamente la struttura `.c-grid` via i loro script (es. `assets/js/admin-logs.js`). Di conseguenza, `Assets::enqueueComponents(['grid'])` includerà il solo CSS del componente a meno che non esista anche un `assets/js/components/grid.js`.

Inline SVG helper:
- Per poter stilare le icone SVG via CSS, il progetto fornisce `src/Core/Svg.php` con il metodo `Svg::inline($file, $attrs = [])` che legge un file SVG da `/assets/imgs/` e lo inietta inline nel markup. Esempio:

```php
use Immaginificio\OAuthProxyBridge\Core\Svg;

// Includi inline l'icona nel markup (puoi passare attributi come class, aria-hidden ecc.)
echo Svg::inline('menu.svg', ['class' => 'menu-icon', 'aria-hidden' => 'true']);
```

Questo permette di colorare/ruotare/modificare l'SVG via CSS senza ricorrere a `<img>`.

---


# 🧪 Standard `@since`

Il progetto usa semantic versioning:

```
MAJOR.MINOR.PATCH
```

Esempio:

- **0.0.01** → prima versione funzionante
- **0.1.00** → nuove funzionalità minori
- **1.0.00** → prima release stabile

Regola:

> Ogni classe/metodo/file deve contenere `@since x.x.xx`.

---

# 🔐 Sicurezza

- Nessun token OAuth salvato sul server
- Protezione cartelle sensibili via `.htaccess`
- Validazione API key via `SiteKey` + `ApiKeyMiddleware`
- State OAuth codificato e validato
- Rate limiting configurabile
- Sessione sicura per pannello admin
- Password hash sicuro (password_hash)

---

# 🛠 Tecnologie utilizzate

- PHP 8+
- Composer + PSR-4
- Hosting condiviso (compatibile con `.htaccess`)
- OAuth 2.0 Authorization Code Flow
- Router custom stile framework

---

# 🧱 Futuri sviluppi

- Provider aggiuntivi: Meta, Apple, LinkedIn
- Dashboard avanzata con grafici e statistiche
- Miglioramento LogMiddleware con livelli log
- API REST dedicate alla gestione dei client

---

## Decisioni di progetto e Roadmap

Queste sono le decisioni attuali prese per lo sviluppo iniziale (confermate dal committente):

- **Target PHP**: PHP 8+.
- **Compatibilità _legacy_**: il codice `_legacy` rimane come riferimento e ambiente di test; non è richiesta retro‑compatibilità obbligatoria con gli URL legacy, servirà solo come guida durante la migrazione.
- **Hosting**: hosting condiviso con possibilità di `cron`. Non assumere availability di worker persistenti.
- **Persistenza token**: Non salvare access/refresh token sul server (stateless bridge).
- **Schema DB**: lo schema SQL può essere modificato quando necessario (è ancora da creare), senza stravolgere la struttura generale.
- **Provider prioritari**: Google (fase iniziale), poi Meta, LinkedIn, Apple.
- **Interfacce**: esporre API REST pubbliche per i client e mantenere la dashboard admin in `views/admin`.
- **Qualità codice**: adottare PSR-12; aggiungere `php-cs-fixer` per formattazione automatica.

Roadmap in fasi:

- **Fase 1 – Core (completamento ~90%)**
	- Router
	- Middleware
	- Request/Response
	- `bootstrap.php`
	- `.htaccess`
	- `index.php`
	- `README`

- **Fase 2 – Google OAuth**
	- Service Manager
	- `GoogleService`
	- `OAuthController` (start/callback/refresh)
	- state encoding/decoding
	- Validazione API key

- **Fase 3 – Admin**
	- Login admin
	- Gestione chiavi siti (add/edit/delete)
	- Visualizzatore log per sito/servizio

- **Fase 4 – Extra provider**
	- Meta
	- LinkedIn
	- Apple

---

# 📄 Licenza

[GPL-3.0-or-later](https://www.gnu.org/licenses/gpl-3.0-standalone.html)

---

# 👤 Autore
Andrea Gaspari - Immaginificio

[GitHub](https://github.com/andreagaspari) - [Sito Web](https://andreagaspari.dev/)
 
---

## 🛠 Strumenti per DocBlock (scan e patch)

Per aiutare la normalizzazione dei DocBlock nel codice `src/` sono presenti due script sotto `tools/`:

- `tools/check_phpdoc.php` — scansione diagnostica che elenca classi e metodi `public`/`protected` privi di DocBlock.
	- Uso: `php tools/check_phpdoc.php`
	- Output: elenco file con riga e tipo (class/method) dove manca la documentazione.

- `tools/add_phpdoc.php` — script di generazione automatica di DocBlock.
	- Uso: `php tools/add_phpdoc.php`
	- Cosa fa: per ogni file in `src/` aggiunge un DocBlock di classe (se mancante) e DocBlock per metodi `public`/`protected` senza DocBlock.
	- Nota importante: lo script è regex-based e applica modifiche conservative; prima di sovrascrivere crea un backup del file modificato con estensione `.bak` (es. `src/Controllers/AdminController.php.bak`).

Linee guida consigliate:

- Eseguire prima la scansione diagnostica:

```bash
php tools/check_phpdoc.php
```

- Se la scansione mostra gap, eseguire lo script di inserimento automatico:

```bash
php tools/add_phpdoc.php
```

- Controllare le modifiche con `git diff` e verificare sintassi prima di committare:

```bash
git --no-pager diff -- src/ | sed -n '1,200p'
php -l $(find src -name '*.php')
```

- Per ripristinare un file modificato dallo script usare il backup `.bak` creato (esempio):

```bash
mv src/Controllers/AdminController.php.bak src/Controllers/AdminController.php
```

Se vuoi, posso:
- aggiungere un comando Composer (`composer run phpdoc:check` / `phpdoc:apply`) per esporre questi script come comandi comodi;
- integrare PHPStan/PHPCS o phpDocumentor nel workflow CI per validare e generare documentazione automaticamente.
