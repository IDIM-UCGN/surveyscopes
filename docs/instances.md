# Running SurveyScopes instances

An *instance* is a deployment of SurveyScopes for one organisation. It lives
in its own repository that pins an upstream version and adds instance-specific
files on top. Updating to a new upstream release must **never lose instance
data**.

## Code vs. data

| Kind | Location | On update |
|------|----------|-----------|
| Application code | this repository | replaced completely |
| Instance preferences | `config/instance.php`, branding (e.g. `assets/img/logo.svg`) | supplied by the instance repo |
| Credentials | `config/secrets.php` | rendered by the instance pipeline |
| Server-side settings | `config/local.php` | **never touched** |
| Runtime files | `data/` | **never touched** |
| User uploads | `uploads/` | **never touched** |
| Database | MySQL | **never touched by deployment**; schema changes via migrations |

## Obligations of this repository

1. The application writes **only** to `data/`, `uploads/`, `config/local.php`
   and the database.
2. The repository never contains files in `data/`, `uploads/` or
   `config/local.php`; the application creates missing directories itself.
3. Schema changes are shipped as forward-only migrations and applied
   automatically (see [architecture.md](architecture.md#7-migrations-and-updates)).
4. A file `/.maintenance` puts the application into maintenance mode (HTTP 503, no writes).
5. Configuration is layered: `defaults.php` → `instance.php` → `secrets.php` → `local.php`.
6. Releases are tagged `vX.Y.Z` with release notes listing breaking changes and new config keys.

## Sending e-mail

The application sends plain-text e-mail (for example to verify an address)
with PHP's own `mail()`, so the server needs a working mail setup (a local
mail transfer agent, or an SMTP server in `php.ini` on Windows). Set the
sender in `config/instance.php` or `config/local.php`:

```php
return ['mail' => ['from' => 'noreply@your-domain.example']];
```

Without a valid `mail.from` nothing is sent. Use an address on your own
domain and set up SPF and DKIM for it, or messages may end up in spam. The
optional `mail.envelope_from` sets the bounce address (ignored on Windows).

For development, `'mail' => ['transport' => 'log']` in `config/local.php`
writes every message as an `.eml` file to `data/mail/` instead of sending it.
Never use it in production: the files can contain live links.

## Instance repository (reference implementation)

```
upstream.env            # UPSTREAM_REPO + UPSTREAM_REF (release tag or commit)
persistent-paths.txt    # data/, uploads/, config/local.php
overlay/                # files copied over the upstream release
templates/              # *.tpl rendered with CI secrets (config/secrets.php)
scripts/build.sh        # fetch upstream @ ref, strip persistent paths, apply overlay
scripts/deploy.sh       # maintenance on → mirror (persistent paths excluded
                        # from upload AND delete) → maintenance off → verify
.github/workflows/      # build on PRs, deploy on main, daily upstream release check
```

Accepting an update = merging the pull request that bumps `UPSTREAM_REF`.
Backups of the database and of `uploads/` are the operator's responsibility
and must exist before an update is merged.
