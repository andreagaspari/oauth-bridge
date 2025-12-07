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
````markdown
# OAuthProxyBridge – Developer Documentation

Welcome to `OAuthProxyBridge` (short-name: `oauth-bridge`), a modular and secure bridge that manages OAuth 2.0 flows between external providers (Google, Meta, Apple, LinkedIn, etc.) and client applications such as WordPress.

This project is organized as a compact PHP micro-framework with routing, middleware, controllers, services and models, designed to run on shared hosting using `.htaccess` when needed.

This developer README covers:
- Architecture overview
- Full folder layout
- Roles of main files
- Development guidelines
- DocBlock conventions
- `@since` usage
- Security notes

---

# Project purpose

OAuthProxyBridge acts as a secure bridge between remote sites/webapps and OAuth2 providers. The bridge:

- Starts OAuth flows (`start`)
- Handles provider callbacks that return `code` and `state`
- Performs the token exchange (access + refresh tokens)
- Supports token `refresh`
- Is stateless regarding end-user tokens (tokens are not persisted by default)
- Validates calling sites using API keys
- Provides a lightweight admin panel for users/keys/logs
- Supports multi-tenant use: multiple sites can access the bridge with separate keys

---

# Server installation

These instructions assume a Linux/Apache-like host. They are conservative — test on staging before production.

Minimum requirements
- PHP 8.0+ with extensions: `pdo`, `pdo_mysql`, `curl`, `mbstring`, `json`, `openssl`
- Composer (for autoload and dependency management)
- MySQL/MariaDB (or another PDO-compatible database) for `site_keys`, `users`, `logs` (optional)
- Apache with `mod_rewrite` (or equivalent nginx configuration)
- HTTPS with a valid certificate (Let's Encrypt recommended)

Installation steps

1) Clone the repo

```bash
git clone https://github.com/andreagaspari/oauth-bridge.git oauth-bridge
cd oauth-bridge
```

2) Install PHP dependencies

```bash
composer install --no-dev --optimize-autoloader
```

3) Environment configuration

- Copy `example.env` (or `.env.example`) to `.env` and adjust values:
  - `DB_DSN`, `DB_USER`, `DB_PASS` — DB connection
  - `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` — Google credentials
  - `APP_ENV=production` and `APP_DEBUG=0` for production

Example `.env` minimal:

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

4) Create database and apply schema

Create the database and user, then import `config/schema.sql`:

```sql
CREATE DATABASE oauth_bridge CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'oauth_user'@'localhost' IDENTIFIED BY 'supersecret';
GRANT ALL PRIVILEGES ON oauth_bridge.* TO 'oauth_user'@'localhost';
```

Import schema:

```bash
mysql -u oauth_user -p oauth_bridge < config/schema.sql
```

5) File ownership and permissions

Make sure the webserver can read the files and write needed directories (cache/upload). Example for Apache on Ubuntu:

```bash
sudo chown -R www-data:www-data /var/www/oauth-bridge
sudo find /var/www/oauth-bridge -type d -exec chmod 755 {} \;
sudo find /var/www/oauth-bridge -type f -exec chmod 644 {} \;
```

6) Apache VirtualHost example

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

Enable `mod_rewrite` and restart Apache:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

7) HTTPS (Let's Encrypt)

```bash
sudo apt install certbot python3-certbot-apache
sudo certbot --apache -d your-oauth-bridge.example
```

8) Additional config

- Configure cronjobs or monitoring tasks for log rotation/cleanup as needed
- Check `config/providers.php` to customize provider redirect URIs

9) Run locally for development

```bash
php -S 127.0.0.1:8000 dev-router.php
```

Post-install checks
- Visit `https://your-oauth-bridge.example/ping` (should respond `OK`)
- Visit `/admin` and create a `site_key` for a client site
- Test the Google flow from a staging client using the recommended server-to-server pattern

Production notes
- Never store `api_key_server` in frontend code. Keep it in server-side secure storage.
- Enable secure logging and log rotation.
- Restrict allowed origins (CORS) if you expose public APIs.

---

# Project layout

```
/
├─ assets/
│   ├─ css/
│   └─ js/
│
├─ config/
│   ├─ config.php            → general configuration
│   ├─ db.php                → database connection helper
│   ├─ providers.php         → provider definitions
│   └─ schema.sql            → DB schema
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
├─ bootstrap.php             → framework bootstrap
├─ routes.php                → route definitions
│
├─ .env                      → environment variables
├─ .htaccess                 → routing + protections
├─ composer.json             → autoload and dependencies
└─ .gitignore
```

---

# Roles of main files

## `index.php` (front controller)
- Entry point for the application
- Loads `bootstrap.php`
- Starts the router and handles top-level exceptions

## `bootstrap.php`
Initializes:
- Composer autoload
- `.env` variables
- Sessions
- Router and global middleware
- Loads `routes.php`

## `routes.php`
Maps URLs to controllers and middleware, e.g.:

```
POST /auth/{provider}/start    → OAuthController::start
GET  /callback                 → OAuthController::callback
POST /auth/{provider}/refresh  → OAuthController::refresh

/admin/*                       → AdminController + AuthMiddleware
```

## `.htaccess`
- Protects sensitive folders: `src/`, `config/`, `vendor/`, `.env`
- Routes requests to `index.php` (single entry point)

## `src/Core`
Lightweight internal framework:
- Custom Router with parameter support (e.g. `{provider}`)
- `Request`, `Response` abstractions
- Session handling
- Admin authentication helper

## `src/Middleware`
Run pre-controller checks:
- API key validation
- Admin authentication
- Rate limiting
- Request logging

## `src/Services`
Provider-specific logic:
- `ServiceInterface.php`
- `ServiceManager.php` (factory)
- `GoogleService.php` (initial provider)

## `src/Models`
Persistent entities:
- `User` (admin)
- `SiteKey` (API key and allowed sites)
- `Log` (request logging)

---

# DocBlock conventions

The project follows this rule:

> Every file, class, method and function should include a complete DocBlock with the following tags:
>- `@package`
>- `@author`
>- `@since`
>- `@param`
>- `@return`

### File DocBlock example

```php
/**
 * Main routing entry for the application.
 *
 * @package OAuthProxyBridge\Core
 * @since 0.0.1
 */
```

### Class DocBlock example

```php
/**
 * Custom HTTP Router with middleware and dynamic parameter support.
 *
 * @since 0.0.1
 */
class Router { ... }
```

### Method DocBlock example

```php
/**
 * Register a new GET route.
 *
 * @param string   $path
 * @param callable $handler
 * @param array    $middleware
 * @return self
 *
 * @since 0.0.1
 */
public function get($path, $handler, array $middleware = []) { ... }
```

---

# Components and assets

To keep views lightweight we include a small asset manager in `src/Core/Assets.php` with helpers to register component CSS/JS.

Main API:

- `Assets::enqueueStyle(string $handle, string $path)` — enqueue a stylesheet
- `Assets::enqueueScript(string $handle, string $path, array $deps = [], bool $inFooter = true)` — enqueue a script
- `Assets::enqueueComponentStyle(string|array $component, ?string $baseDir = null)` — enqueue a component CSS under `/assets/css/components/{name}.css`
- `Assets::enqueueComponentScript(string|array $component, ?string $baseDir = null, array $deps = [], bool $inFooter = true)` — enqueue a component JS under `/assets/js/components/{name}.js`
- `Assets::enqueueComponents(array $components, ?string $cssBase = null, ?string $jsBase = null, array $defaultDeps = [], bool $defaultInFooter = true)` — batch enqueue multiple components

Quick examples (before including `layout.php`):

```php
use Immaginificio\OAuthProxyBridge\Core\Assets;

Assets::enqueueComponentStyle('button');
Assets::enqueueComponentScript('toggle-password');
Assets::enqueueComponents(['button','icon-button','card','field','input-group','grid','icon']);
Assets::enqueueScript('admin-login','/assets/js/admin-login.js', [], true);
```

Notes:
- Views should not print `<link>` / `<script>` tags directly when using `Assets` — the layout (`views/admin/layout.php`) calls `Assets::printStyles()` and `Assets::printScripts()`.
- `Assets` appends `?v=<mtime>` to local asset URLs for cache busting.
- `enqueueComponents()` expects component names under `components/`. Passing a full path (e.g. `/assets/js/admin-login.js`) will be used as provided.
- If `Assets` is not available during bootstrap, views fall back to static includes.

Grid note:
- There is a `assets/css/components/grid.css` component for responsive grids. The previous JS-based table-to-grid transformer was removed; admin pages now render `.c-grid` structure directly. `Assets::enqueueComponents(['grid'])` will include only the CSS unless a corresponding JS exists.

Inline SVG helper:
- Use `src/Core/Svg.php` with `Svg::inline($file, $attrs = [])` to inline SVG from `/assets/imgs/` and provide attributes like `class` or `aria-hidden`.

```php
use Immaginificio\OAuthProxyBridge\Core\Svg;
echo Svg::inline('menu.svg', ['class' => 'menu-icon', 'aria-hidden' => 'true']);
```

---

# `@since` standard

The project uses semantic versioning:

```
MAJOR.MINOR.PATCH
```
````
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
