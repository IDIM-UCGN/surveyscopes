# SurveyScopes – Architecture

Binding guidelines for the structure and implementation of SurveyScopes,
derived from the [principles](principles.md).
Every contribution follows them; deviations need an explicit decision
documented in this file.

## 1. Principles

| Principle | Rule |
|-----------|------|
| Runtime | **PHP 8** (minimum 8.0, CI tests 8.0 and the latest 8.x) |
| Database | **MySQL**, current release (8.4 LTS or newer), InnoDB, `utf8mb4` |
| Dependencies | **None by default** – no Composer packages, no npm build, no framework |
| Frontend | **Bootstrap 5**, vendored in the repository; **no CDN**, no external fonts/scripts |
| JavaScript | Plain JS, optional enhancement only – every core function works without JS |
| Pages | **Flat PHP files**, one file per page, no front controller framework |
| URLs | Clean URLs without `.php`, language prefix (`/de/…`, `/en/…`) |
| Hosting | Apache with `mod_rewrite` / `.htaccess`, deployable via FTP(S) to shared hosting |
| Languages | **Multi-language** throughout: UI, admin-editable pages, surveys |
| Instances | Upstream code is replaceable without touching instance data (see [instances.md](instances.md)) |
| License | MIT |

## 2. Directory layout

The repository root **is** the web root (shared hosting often allows no
directory above it). Internal directories are blocked by `.htaccess`.

```
.htaccess                 # www/HTTPS redirect, clean URLs, language routing, security headers
VERSION                   # application version, e.g. 1.2.0
index.php                 # front page: admin-editable intro + list of public surveys
surveys.php               # public survey list (all public, open surveys)
survey.php                # take a survey:  /<lang>/survey/<slug>
thanks.php                # confirmation after submitting
imprint.php               # imprint, content set by site admin
privacy.php               # privacy policy, content set by site admin
login.php, logout.php
media.php                 # serves files from uploads/ after permission checks
language.php              # no-JS fallback of the footer language dropdown
sitemap.php, robots.php   # served as /sitemap.xml and /robots.txt
404.php
admin/                    # site administration (login required)
  index.php               # dashboard
  surveys.php             # list / create surveys
  survey-edit.php         # edit survey, questions, translations
  results.php             # responses, CSV export
  pages.php               # front page, imprint, privacy (per language)
  settings.php            # site name, languages, public list on/off, contact
  users.php               # accounts and roles
assets/                   # public static files
  css/app.css
  js/app.js
  img/logo.svg            # simple default logo (instances may override)
  vendor/bootstrap-5.x.y/ # vendored Bootstrap (css + bundle js + LICENSE)
src/                      # PHP code, never web-accessible
  bootstrap.php           # loads config, starts session, sets error handling
  config.php              # layered config loader
  db.php                  # PDO connection + query helpers
  auth.php                # login, roles, password hashing
  csrf.php                # CSRF tokens
  i18n.php                # language detection, translation helpers
  view.php                # escaping, layout rendering, minimal markup renderer
  migrate.php             # automatic schema migrations
  repositories/           # data access per entity (surveys, responses, pages, users …)
  migrations/             # 0001_init.sql, 0002_….sql – forward-only
templates/                # layout and partials (header, footer, forms); not web-accessible
lang/                     # UI strings: de.php, en.php, …; not web-accessible
config/
  defaults.php            # shipped defaults
  .htaccess               # Require all denied
# created on the server, never in the repository:
config/instance.php       # instance preferences (instance repo overlay)
config/secrets.php        # credentials (rendered at deploy time)
config/local.php          # settings written on the server (installer)
data/                     # runtime files (locks, caches, exports)
uploads/                  # files uploaded by users
.maintenance              # present during deployments → HTTP 503
```

Blocked over HTTP (403/404): `src/`, `templates/`, `lang/`, `config/`,
`data/`, `uploads/`, `VERSION`, dotfiles except `.well-known/`.

## 3. Request flow and routing

### 3.1 `.htaccess`

1. `surveyscopes.example` → `https://www.surveyscopes.example` (301); HTTP → HTTPS (301).
   The canonical host is configurable per instance.
2. Requests for internal directories → 404.
3. `/<page>.php` → `/<page>` (301); `/index.php` → `/` (301).
4. `/<lang>/` → `index.php`; `/<lang>/<page>` → `<page>.php` if the file exists.
5. Pretty parameters: `/<lang>/survey/<slug>` → `survey.php?slug=<slug>`,
   `/<lang>/admin/<page>` → `admin/<page>.php`.
6. URLs without language prefix (`/`, `/<page>`) → PHP redirects (302,
   `Vary: Accept-Language`) to the best language from `Accept-Language`,
   falling back to the instance default language.

The language code is read from the **URL path**, never from a query
parameter, so every page has exactly one canonical URL per language.

### 3.2 Page skeleton

Every page file follows the same pattern:

```php
<?php
declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';   // config, session, i18n, db (lazy), migrations
page('surveys');                           // language from URL or redirect, sets current page
// require_role('admin');                  // for admin pages

$surveys = Surveys::publicOpen(lang());

render('surveys', ['surveys' => $surveys]); // templates/surveys.php inside layout
```

Rules:

- `declare(strict_types=1);` in every PHP file.
- No HTML outside `templates/`; no SQL outside `src/repositories/`.
- State-changing actions only via `POST` + CSRF token, then redirect (PRG pattern).
- No output before headers; one `render()` call per request.

## 4. Multi-language

Languages are configured per instance (`available_locales`, `default_locale`).
Adding a language never needs a code change beyond a new `lang/<code>.php`.

| Layer | Storage | Fallback |
|-------|---------|----------|
| UI strings | `lang/<code>.php` returning `['key' => 'text']` | default locale → key |
| Front page, imprint, privacy | `page_translations` (per page and locale) | default locale |
| Surveys, questions, options | `*_translations` tables | survey's own default locale |
| Dates, numbers | `IntlDateFormatter` if `intl` is available, else built-in formats | – |

- URL prefix defines the language: `/de/…`, `/en/…`.
- The **language chooser is a dropdown (`<select>`) in the footer** only, without
  a visible label (accessible name via `aria-label`). It lists the same page in
  every language; `assets/js/app.js` switches on change, without JavaScript a
  small submit button posts to `/language?to=<local URL>` (validated redirect).
- `hreflang` alternates for every language plus `x-default` are set in `<head>`.
- Survey participants see the survey in the current URL language if a
  translation exists, otherwise in the survey's default locale (with a notice).
- Each response stores the locale in which it was given.
- Admins edit all translations of an item on one screen (tabs per language);
  missing translations are highlighted.

## 5. Content managed by the site admin

Stored in the database, edited under `admin/pages.php` and `admin/settings.php`:

- **Front page**: headline, intro text, optional call-to-action, per language;
  toggle whether the public survey list is shown below.
- **Public survey list**: surveys flagged `is_public` with status `open`
  (and inside their open/close dates) appear on the front page and on `/<lang>/surveys`.
  Non-public surveys are only reachable via their direct link.
- **Imprint** and **privacy policy**: free text per language. Both pages are
  linked in the footer on every page; until content exists, the pages show
  a notice to admins and a 404 to visitors.
- **Site settings**: site name, contact e-mail, available/default languages.

Text content uses a **minimal markup** (paragraphs, `##`/`###` headings → `<h2>`/`<h3>`,
`**bold**`, `*italic*`, lists, links) rendered by `src/view.php`.
Everything is HTML-escaped first; no raw HTML is stored or output.

### Logo and theme

A simple SVG logo ships at `assets/img/logo.svg` and is used in the header
and as favicon. Instances replace it via their overlay (same path).

The default theme (`assets/css/app.css`) is a modern green design on top of
Bootstrap, built from `--ss-*` CSS custom properties. Instances adjust it with
a stylesheet referenced by the `custom_css` config key that overrides those tokens.

### Instance seed: initial content as user data

Settings and page contents live in the **database** (`settings`,
`pages`, `page_translations`) – they are instance data, not code, and are
never overwritten by a deployment.

An instance provides its initial content as a **seed** in `config/instance.php`
(`'seed' => ['version' => n, 'settings' => …, 'pages' => …]`, shape documented
in `config/defaults.php`). On the first request after a deployment:

- missing rows are inserted with `source = 'seed'`;
- if `seed.version` is higher than the stored `_seed_version`, rows that
  still have `source = 'seed'` are updated;
- rows edited by the site admin (`source = 'admin'`) are **never** changed.

Without a configured database (local development) the seed is used read-only.

## 6. Database

MySQL, InnoDB, `utf8mb4` / `utf8mb4_unicode_ci`, strict SQL mode.
All tables carry `created_at` / `updated_at` (`DATETIME`, UTC).

Core tables (initial migration):

| Table | Purpose |
|-------|---------|
| `schema_migrations` | applied migrations (`version`, `applied_at`) |
| `users` | accounts: e-mail, `password_hash`, role (`admin`, `editor`), status |
| `settings` | key/value instance settings (JSON values, `source` = seed/admin) |
| `pages` / `page_translations` | `front`, `imprint`, `privacy` × locale: title, body, meta description, `source` |
| `surveys` | slug, status (`draft`/`open`/`closed`), `is_public`, `default_locale`, opens/closes at, owner |
| `survey_translations` | survey × locale: title, description, thank-you text |
| `questions` / `question_translations` | position, type, required, settings JSON / text, help text |
| `question_options` / `option_translations` | choice options per question / labels |
| `responses` | survey, locale, submitted_at, optional anonymous token |
| `answers` | response × question: value (text / option id / JSON for multi) |
| `files` | metadata of uploaded files (path in `uploads/`, mime, size, owner) |

Rules:

- PDO with `ERRMODE_EXCEPTION`, prepared statements only, no string-built SQL.
- Foreign keys with `ON DELETE` behaviour defined explicitly.
- No personal data in URLs or logs; IP addresses are not stored with responses.

## 7. Migrations and updates

- Migrations live in `src/migrations/NNNN_description.sql`, are
  **forward-only**, idempotent where possible, and never drop user data
  without a prior copy step.
- `src/migrate.php` runs on bootstrap when `VERSION` is newer than the last
  applied migration set: it takes a MySQL `GET_LOCK()`, applies pending
  migrations in order, records them in `schema_migrations`, releases the lock.
  Concurrent requests wait or get HTTP 503.
- While `/.maintenance` exists, every request returns HTTP 503 with a
  maintenance page and performs no writes.

## 8. Configuration

Loaded in this order, later files override earlier ones (`array_replace_recursive`):

1. `config/defaults.php` – shipped with the code
2. `config/instance.php` – instance preferences (deployed from the instance repo)
3. `config/secrets.php` – credentials (`app_key`, `database`) rendered at deploy time
4. `config/local.php` – written on the server (e.g. by the installer)

Each file `return`s an array. Missing optional files are skipped.

```php
// config/secrets.php (example shape)
return [
    'app_key' => '…random…',
    'database' => [
        'driver' => 'mysql', 'host' => 'localhost', 'port' => 3306,
        'name' => '…', 'user' => '…', 'password' => '…', 'charset' => 'utf8mb4',
    ],
];
```

## 9. SEO

- One canonical URL per page and language (`<link rel="canonical">`),
  `hreflang` alternates for all languages plus `x-default`.
- Unique `<title>` (`page – site name`) and `<meta name="description">` per
  page; page descriptions are editable by the site admin (`meta_description`).
- Open Graph and Twitter card tags with a 1200×630 share image
  (`assets/img/og-image.png`, setting `og_image`).
- JSON-LD (`schema.org/WebSite`) on the front page.
- `/sitemap.xml` (all public pages × languages with `xhtml:link` alternates)
  and `/robots.txt` referencing it, both generated by PHP.
- Pages without content (e.g. empty imprint) and error pages are `noindex`.
- Semantic HTML: one `<h1>` per page, landmarks, `lang` attributes.

## 10. Security

- Escape all output with `e()`; templates never echo raw variables.
- Sessions: `HttpOnly`, `Secure`, `SameSite=Lax`, ID regenerated on login.
- Passwords: `password_hash()` / `password_verify()` with `PASSWORD_DEFAULT`;
  login throttling per account and IP (stored in DB, IP hashed).
- CSRF token on every form.
- Headers: CSP `default-src 'self'` (no inline scripts/styles), `X-Content-Type-Options`,
  `Referrer-Policy`, `X-Frame-Options: DENY`, HSTS.
- Uploads: stored under `uploads/` with random names, MIME type checked with
  `finfo`, size-limited, served only via `media.php` after permission checks,
  with `Content-Disposition` and `X-Content-Type-Options: nosniff`.
- No cookies besides the session cookie; no tracking; no third-party requests.

## 11. PHP extensions

Required: `pdo_mysql`, `mbstring`, `json`, `fileinfo`, `session`.
Optional: `intl` (localised date/number formats).

## 12. Development and CI

- Local: `php -S localhost:8000 -t . tools/dev-router.php` (router emulating `.htaccess`).
- CI: `php -l` and smoke tests on PHP 8.0 and latest 8.x; migrations and seed
  sync (incl. "admin edits survive a newer seed") against a MySQL 8.4 service container.
- `php tools/migrate.php` applies migrations from the command line.
- Coding style: PSR-12, `declare(strict_types=1)`, typed parameters and returns.
- Releases: semantic version tags `vX.Y.Z` with GitHub release notes; breaking
  changes and new config keys are listed explicitly.
