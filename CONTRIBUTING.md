# Contributing to SurveyScopes

Thank you for helping! SurveyScopes follows a small set of
[principles](docs/principles.md) and an [architecture](docs/architecture.md)
that every contribution respects.

## Who does what

| Role | Access | Workflow |
|------|--------|----------|
| **Maintainers** (2 admins) | Admin | may push to `main` directly; review and merge pull requests |
| **Team contributors** | Read | work in a **fork** and open pull requests |
| **External contributors** | – | work in a **fork** and open pull requests |

Nobody except the maintainers can push to this repository; every other change
arrives as a pull request from a fork and is reviewed before it is merged.

## One-time setup

1. Click **Fork** on GitHub and create the fork under your own account.
2. Clone it and add this repository as `upstream`:

   ```sh
   git clone https://github.com/<your-name>/surveyscopes.git
   cd surveyscopes
   git remote add upstream https://github.com/IDIM-UCGN/surveyscopes.git
   ```

3. **Commit with your university email.** You may use a private GitHub
   account, but commits must always carry your `@uni-koeln.de` address.
   Set it for this clone only (no `--global`):

   ```sh
   git config user.name "First Last"
   git config user.email "<name>@uni-koeln.de"
   ```

   Add the address to your GitHub account (*Settings → Emails*) so commits are
   linked to your profile.

## For every change

1. **Update your fork:**

   ```sh
   git switch main
   git fetch upstream
   git merge --ff-only upstream/main
   git push origin main
   ```

   Or on GitHub in your fork: **Sync fork** → *Update branch*.

2. **Create a branch** (`feature/…`, `fix/…`, `docs/…`):

   ```sh
   git switch -c feature/short-description
   ```

3. **Change, test locally, commit, push to your fork:**

   ```sh
   php -S localhost:8000 tools/dev-router.php
   find . -path ./.git -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
   git commit -am "Short, meaningful description"
   git push origin feature/short-description
   ```

4. **Open a pull request** against `IDIM-UCGN/surveyscopes` → `main`,
   fill in the template and keep **“Allow edits by maintainers”** enabled.

5. **Review:** a maintainer reviews it. CI (PHP 8.0 + 8.4 lint and smoke
   tests, MySQL migrations) runs after a maintainer has approved the workflow
   run. Pull requests are merged with **squash** – one commit per pull request.

## Rules

- **No new dependencies**: PHP 8 + MySQL only; no Composer packages, no npm
  build, no CDN, no external fonts or scripts.
- **PHP 8.0 compatible** code with `declare(strict_types=1);`, PSR-12 style.
- **Multi-language**: every UI string goes into all files in `lang/`.
- **Instance data is sacred**: write only to `data/`, `uploads/`,
  `config/local.php` and the database; schema changes only as new,
  forward-only migrations in `src/migrations/` (never edit an applied one).
- Escape all output (`e()`, `t()`), prepared statements only, CSRF on forms.
- No credentials or personal data in commits, issues or pull requests.
- One topic per pull request; update the docs when behaviour changes.

## For maintainers

- Review checklist: principles respected, CI green, both languages, no new
  dependencies, migrations forward-only, docs updated.
- Merge with **Squash and merge** only; branches are deleted automatically.
- Releases: bump `VERSION`, tag `vX.Y.Z`, publish release notes listing
  breaking changes and new config keys – instances pick them up via their
  update pull request.

## License

By contributing you agree that your contribution is licensed under the
[MIT License](LICENSE).
