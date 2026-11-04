# SurveyScopes – Principles

The fundamental decisions of the project. They take precedence over
convenience; changing one requires an explicit decision recorded here.
Implementation details follow in [architecture.md](architecture.md).

## Project

1. **Open source under the MIT License**, developed in the context of the
   University of Cologne.
2. **Three kinds of repositories**:
   - `surveyscopes` – this repository, the core software (public).
   - `surveyscopes-org` – project website <https://www.surveyscopes.org>.
   - instance repositories (e.g. `surveyscopes-com`) – one per operator; they
     pin a core version and add only their own preferences.
3. **Continuous deployment**: every repository deploys automatically from
   `main` via CI/CD; nothing is uploaded by hand.

## Technology

4. **PHP 8** and **MySQL** (current release) – and **no other dependencies by
   default**: no Composer packages, no npm build step, no framework.
5. **Bootstrap 5, vendored – no CDN.** No external fonts, scripts, styles or
   other third-party requests. Everything is served from the instance's own server.
6. **Flat PHP files**: one file per page, simple and readable.
7. **Clean URLs**: no `.php` extension in URLs (`.htaccess`); requests to the
   bare domain redirect to the `www.` host over HTTPS.
8. Runs on ordinary **shared hosting** (Apache + `.htaccess`, FTP(S) deployment).

## Languages

9. **Multi-language throughout**: user interface, admin-managed pages and
   surveys are translatable; adding a language requires no code change
   beyond a language file.
10. **Language in the URL path**: `/de/…`, `/en/…`. URLs without a language
    redirect according to the browser language, falling back to English
    (or the instance's default language).
11. **Language chooser only at the bottom of the page** (footer).

## Content and administration

12. **Simple logo**, replaceable per instance.
13. The **site admin** sets the **front page information**, decides on the
    **public list of surveys**, and maintains the **imprint** and **privacy
    policy** content – per language, without touching code.

## Instances and updates

14. Each instance **downloads the official core release** and adds its own
    preferences on top; it never forks the core code.
15. A new core version is **accepted per instance** (pull request in the
    instance repository) and then deployed automatically.
16. **Updates never lose data**: survey data, responses, user uploads and
    server-side settings are preserved across every update – on every instance.
    See [instances.md](instances.md).

## Privacy and security

17. **Privacy by design**: no tracking, no analytics, no cookies for
    participants; only data that a survey actually needs is stored.
18. Secure defaults: escaped output, prepared statements, CSRF protection,
    strict security headers, internal directories never web-accessible.
