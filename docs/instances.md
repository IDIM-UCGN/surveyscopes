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

## First administrator and access recovery

An instance has no accounts until the first administrator is created. This
needs a database and an `app_key` of at least 32 characters in
`config/secrets.php`. There are two ways, with the same result:

- **Setup page, without shell access (FTP is enough).** Create the file
  `data/setup-token` on the server and put one random value of at least 32
  characters in it (for example from a password manager). While the file
  exists and is at most 24 hours old, `/<lang>/setup` accepts that token, an
  e-mail address and a password (12 characters to 72 bytes). On success the
  account is created and **the page deletes the token file**; without the
  file the page does not exist (404). Wrong tokens are throttled per client
  address.
- **Command line:** `php tools/create-admin.php <email> [--update]` prompts
  for the password twice. The password is hidden on Linux and macOS but
  **visible on Windows**. `SS_PASSWORD` is read only in CI (`CI=true`).

Both also recover access: for an e-mail address that already exists, the
setup page and `--update` make the member an active administrator with the
new password and clear its login lockout. Whoever can write `data/` or run
the command is therefore trusted at operator level, like anyone with access
to the database. Deployments never touch `data/`, so a token file that was
not used stays there and valid for 24 hours: delete it if you no longer need it.

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
