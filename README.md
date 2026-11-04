# SurveyScopes

Open-source survey platform, released under the [MIT License](LICENSE) and
developed in the context of the University of Cologne.
Project website: <https://www.surveyscopes.org>

> **Status:** early development (0.1.0 – public skeleton: front page, survey
> list placeholder, imprint/privacy, languages). Database, admin area and
> surveys follow.

## How to contribute

1. **Fork** this repository on GitHub and clone your fork.
2. **Create a branch** (`feature/…`, `fix/…`, `docs/…`), make your change and test it locally.
3. **Commit and push** to your fork:
   `git commit -m "Short, meaningful description"` → `git push origin <branch>`
4. **Open a pull request** against `IDIM-UCGN/surveyscopes` → `main`.
   A maintainer reviews and merges it (squash, one commit per pull request); instances pick up the change with the next release.

Only the two maintainers push to `main` directly. No credentials or personal
data in commits. Details: [CONTRIBUTING.md](CONTRIBUTING.md).

## At a glance

- **PHP 8** and **MySQL** (current release) – no other runtime dependencies by default
- Flat PHP files, clean URLs without `.php`, Apache `.htaccess` (www/HTTPS redirect)
- **Bootstrap 5** vendored in the repository – no CDN, no third-party requests
- **Multi-language**: UI, front page, imprint, privacy and surveys per language (`/de/…`, `/en/…`)
- Site admin manages front page information, the public survey list, imprint and privacy policy
- Simple default logo, replaceable per instance
- Instance-friendly: updates never touch survey data or uploads

## Documentation

- [docs/principles.md](docs/principles.md) – the fundamental project decisions
- [docs/architecture.md](docs/architecture.md) – structure, routing, multi-language, database, security
- [docs/instances.md](docs/instances.md) – running your own instance and updating it safely
- [CONTRIBUTING.md](CONTRIBUTING.md) – how to submit changes (fork + pull request)

## Requirements

- PHP ≥ 8.0 with `pdo_mysql`, `mbstring`, `json`, `fileinfo` (optional: `intl`)
- MySQL 8.4 LTS or newer
- Apache with `mod_rewrite`

## Local development

```sh
php -S localhost:8000 tools/dev-router.php
```

## License

MIT – see [LICENSE](LICENSE).
