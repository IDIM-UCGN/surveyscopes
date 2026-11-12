# Survey roles, participant access and invitations (proposal)

**Status:** proposal for review, updated after the maintainers' first feedback. Nothing in this document is implemented. Where the text says "proposed", it can be accepted or changed; "open question" marks something that still needs an answer.

**Author / date:** _Stebin Thomas / 12.11.2026

## 1. Summary

SurveyScopes accounts carry only an **admin flag** (yes/no). Everything else is a **role per survey**: *editor* or *participant*.

- A **participant** is a person who logs in and can see only the surveys they were added to.
- Participants are **added by an editor of a survey** when that survey requires a login. They then receive an email with the survey info text:
  - a person **without an account** gets a **registration link** and is told which email address the invitation is connected to; registering with that address gives access to the survey;
  - a person who is **already registered** with that address gets a link to the survey.
- Each survey has an **answer mode**:
  - **anonymous**: no login; reachable through a **public link that is also listed on the website**, or through a **secret link**;
  - **identified**: login required; answers are linked to the participant's account.
- A small **mail layer** using PHP `mail()`.
- An **audit log**.
- A sequence of small pull requests.

All code stays within the existing rules: PHP 8.0 compatible, no Composer packages, no new JavaScript requirements, SQL only in `src/repositories/`, CSRF on every POST, PRG with 303.

## 2. Decisions

| # | Topic | Status |
|---|---|---|
| 1 | **Principle 19.** No tracking, no analytics, no third-party requests; technically necessary cookies (for example for login) are allowed. | Decided by the maintainers. New wording in section 4. |
| 2 | **Roles.** `participant` is *not* added to a global role list. Accounts get an admin flag; editor and participant are roles per survey. | Decided; change proposal for `architecture.md` section 6 in section 4. |
| 3 | **PR (a).** Replace the `role` column by an `is_admin` flag. | Decided: a migration sql task. |
| 4 | **Timestamps on anonymous responses.** | Decided: date only. |
| 5 | **ASCII-only email addresses**, `utf8mb4_bin` collation for email columns. | Decided: yes. |
| 6 | **Mail.** PHP `mail()` as the first transport. | Decided: yes. |

## 3. Roles and permissions

There are three kinds of access: the global **admin flag**, a **role per survey** (editor or participant), and **visitors without an account** (anonymous surveys only).

| Capability | Admin (flag) | Editor of that survey | Participant of that survey | Visitor, no account |
|---|---|---|---|---|
| Site settings, logo, front page, imprint, privacy | yes | no | no | no |
| Manage accounts and the admin flag | yes | no | no | no |
| Create surveys (open question 1) | yes | no | no | no |
| Assign editors to a survey | yes | no | no | no |
| Edit the survey, questions, translations | all surveys | yes | no | no |
| Add and remove participants | all surveys | yes | no | no |
| View results and exports | all surveys | yes | no | no |
| See audit entries | all | entries about that survey | no | no |
| Preview (stores nothing) | yes | yes | no | no |
| Take the survey | identified surveys | identified surveys | identified surveys | anonymous surveys (public or secret link) |
| Admin area | all pages | survey pages of their surveys | no, "my surveys" page only | no |

- A person has at most one role per survey. They can be editor in one survey and participant in another.
- The last active admin cannot be demoted, disabled or deleted. This is checked in a transaction with `SELECT ... FOR UPDATE`. Setup and the CLI remain as recovery paths.
- The admin flag is set only by an admin (or by setup and the CLI). A request field never sets it.
- Access to a specific survey is always a server-side check on the survey membership, never a rank comparison.
- Landing page after login: one dashboard, but different modular elements (admins settings, surveys).
- Keep in mind, that a participant account can also get an editor role of a survey and vice versa

## 4. Changes to existing principles and architecture

Proposed text for `principles.md` #19:

> No tracking, no analytics, no third-party requests. Only technically necessary cookies are used (for example the session cookie for logged-in users). Visitors of anonymous surveys get no cookie.

Proposed changes to `architecture.md`:

| Section | Today | Proposed |
|---|---|---|
| §2 | bootstrap starts the session | The session is lazy: it starts only on login, logout, setup, registration and account pages, and is stored in `data/sessions/`. Public pages never set a cookie. |
| §6 `users` | e-mail, password_hash, role (admin, editor), status | e-mail, password_hash, **is_admin** (flag), status, last_login_at. Email stored normalised, `utf8mb4_bin`, ASCII only. |
| §6 `surveys` | slug, status, is_public, default_locale, opens/closes at, owner | slug, status, is_public, default_locale, opens/closes at, **created_by**, **answer_mode**. The single owner is replaced by editor roles in `survey_members`. |
| §6 new | none | **`survey_members`**: which account has which role in which survey. |
| §6 new | none | `invitations`, `invitation_nonces`, `invitation_sends`, `audit_log` (sections 6 and 8). |
| §6 `responses` | optional anonymous token | Identified mode: `user_id`, draft or submitted. Anonymous mode: no `user_id`, random id, date-only timestamps if decision 4 is accepted. |
| §6 timestamps | exact `created_at` / `updated_at` everywhere | Exception for responses of anonymous surveys (decision 4). |
| §6 `login_attempts` | not listed | Added. No foreign key on purpose, because it also throttles addresses that do not exist. |
| §10 | no cookies besides the session cookie | Consistent with the new principle 19. Adds throttling keys, password length (12 characters to 72 bytes), idle and absolute session lifetime, hashed-IP retention. |
| §10 mail | none | PHP `mail()`; mail layer rules (section 7). |

## 5. Surveys and answer modes

The editor or admin **must choose** the mode when creating a survey (no default, no preselected option). no lock required. the repository changes it only `WHERE answer_mode_locked_at IS NULL`. The mode is shown on the first page of the survey and stated in the invitation email.

**Anonymous**
- No login, no cookie, no account involved.
- **Public link:** the survey is flagged public and listed on the front page and the survey list, as `architecture.md` already describes.
- **Secret link:** the survey is not listed. The system generates a long random slug that cannot be edited, so the link cannot be guessed. Survey pages of secret surveys send `Referrer-Policy: no-referrer` and `noindex`, and are excluded from the sitemap.
- Answers are submitted in one go. No state is kept for visitors, so there are no drafts.
- Duplicate submissions cannot be prevented, because nobody is identified. Rate limiting per IP can reduce abuse.
- Responses have a random id (`CHAR(32)`) instead of an auto-increment. Timestamps: **decision 4.** Without accounts, the system cannot link an answer to a person, but an exact timestamp can still be matched with server logs or with a small group that was given a secret link. Options: (A) exact timestamps as `architecture.md` §6 requires; (B) date only for responses of anonymous surveys (a recorded deviation). Proposed: B.
- Results are shown, and exports allowed, only when at least `results.min_group_size` responses exist (proposed 5). No filters or cross-tabulations in v1. No date column in exports.

**Identified**
- Login is required. Only members with the role *participant* can answer.
- `responses.user_id`, status draft (each field saved automatically) or submitted; answers can be resumed and (proposed) edited until the survey closes.
- Never listed publicly: a database check requires `answer_mode = 'anonymous' OR is_public = 0`.
- Answers are personal data, so the invitation email says that the answers are linked to the account.

## 6. Adding participants (identified surveys)

### Tables (sketch, not final SQL)

**`survey_members`**: `survey_id` -> surveys (CASCADE), `user_id` -> users (CASCADE), `role` (`editor` or `participant`), `added_by` -> users (SET NULL), `joined_at` NULL (set at first access to the survey), timestamps. PK `(survey_id, user_id)`.

**`invitations`**: only for addresses that have **no account yet**. `id`, `survey_id` -> surveys (CASCADE), `email VARCHAR(254)` (`utf8mb4_bin`, normalised), `token_hash CHAR(64)` (SHA-256 of a 32-byte random token), `invited_by` -> users (SET NULL), `expires_at`, `used_at`, `used_by` -> users (SET NULL), `revoked_at`, `send_count`, `last_sent_at`, timestamps, plus an "open" marker so only one open invitation per survey and address can exist while used and revoked rows stay as history.

*Open marker:* a generated column with a `UNIQUE` index would be neat, but MySQL restricts `ON DELETE CASCADE` on the base column of a stored generated column. **Step 0 (test on MySQL 8.4 before the invitation PR)** checks that the table can be created, that a second open row is rejected, that a used or revoked row plus a new open row are accepted, that deleting the survey cascades, and that setting `used_at` frees the key. If any check fails, use an application-maintained `open_marker TINYINT NULL` with `UNIQUE (survey_id, email, open_marker)`, set to `NULL` in the same `UPDATE` that sets `used_at` or `revoked_at`. The result goes into the description of that pull request.

**`invitation_nonces`**: `id`, `invitation_id` -> invitations (CASCADE), `nonce_hash CHAR(64)` UNIQUE, `expires_at`, timestamps. At most 10 per invitation (the oldest is deleted when an 11th is added). Several nonces are allowed because mail link scanners open the link before the person does.

**`invitation_sends`**: `id`, `survey_id` -> surveys (CASCADE), `sent_by` -> users (SET NULL), `sent_at`. One row per email sent (both kinds), as the basis for the editor limits.

### Adding a participant (editor or admin)

1. The server checks that the user is an admin or an editor of that survey, that the survey is in identified mode, and that it is not closed.
2. The address must pass the ASCII email check. The form states that non-ASCII addresses are not supported.
3. Editor limits (20 emails per hour, 100 per day, configurable) are checked against `invitation_sends`.
4. The server looks up the normalised address in `users`:
   - **Active account:** a `survey_members` row with role participant is added (if not already a member), and the person gets the "you were added" email with the survey info text and a link to the survey. They must log in to open it.
   - **Disabled account:** nothing is sent; the editor sees the same message.
   - **No account:** an open invitation is created or refreshed (rules below) and the email contains the registration link.
5. The editor always sees the same message ("Participant added; an email was sent"). In the member list, everyone shows as "invited" until `joined_at` is set at their first access, so the editor cannot learn whether an address already has an account.

### Rules for an invitation to an address without an account

| State of the existing row | Behaviour |
|---|---|
| open, not expired | Re-send: new token (the old link stops working), new expiry, `send_count` + 1. Refused at 5 sends and within 10 minutes of the last send. |
| open, expired | Same as above. |
| used | The address now has an account; the "active account" path applies. |
| revoked | New row, `send_count` = 1. |

Removing a participant deletes the `survey_members` row, revokes an open invitation (sets `revoked_at`, clears the marker, deletes the nonces) and writes an audit entry. Their identified answers stay (proposed; final rule in the answers PR).

### The emails

Plain text, UTF-8, language chosen from the survey's translations (default: the survey's default locale). Subject: fixed translated text with the site name. Body:

- fixed translated text;
- the survey title and the **survey info text** (the survey's description in that language), both cleaned as described in section 7;
- the statement of the answer mode ("your answers are linked to your account");
- **for a new address:** the email address the invitation is connected to, the registration link and the expiry date;
- **for an existing account:** a link to the survey;

### Registration and accepting a link (participant)

1. `GET /<lang>/register/<token>`: an unknown, expired, used or revoked token gets one generic message and counts against the IP throttle. Headers: `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`. If the token is valid, a nonce is created (its hash stored) and **only the invitation ID and the nonce** go into the session, never the token. Then a 303 to `/<lang>/register`, a URL without the token. Opening the link does not use it up.
2. `GET /<lang>/register` loads the invitation by ID and nonce hash. If an account with that address has appeared in the meantime, the person must log in instead, and the account's address must be byte-identical to the invitation's. On a mismatch nothing is granted: the page explains this and offers a logout button (POST + CSRF). There is no automatic logout, and the invitation stays valid.
3. Otherwise the page shows the registration form. The address comes from the invitation and is not an input field. The person sets a password (12 characters to 72 bytes, entered twice).
4. `POST`, in one transaction: CSRF check; password rules; an atomic `UPDATE invitations SET used_at = ?, used_by = ?, <marker> = NULL WHERE id = ? AND used_at IS NULL AND revoked_at IS NULL AND expires_at > ?` that must change exactly one row; the account is created with the admin flag **off**, set in code; the `survey_members` row is inserted; the nonces are deleted; an audit entry is written; `session_regenerate_id(true)`; 303 to the survey.
5. Registration is possible only through an invitation. Control of the mailbox is the verification of the address. Accounts cannot change their email address in this design; if that is added later, the new address must be verified, because membership matching uses the address.

### Purge

On about 1 in 100 invitation requests, invitations whose `used_at`, `revoked_at` or (for open ones) `expires_at` is more than `invitations.retention_days` (30) old are deleted with their nonces. Expired nonces and `invitation_sends` older than 24 hours are deleted on every invitation request.

## 7. Mail layer

- `send_mail(string $to, string $subject, string $body): bool`.
- `$to` and `mail.from` must pass the ASCII email check (no CR/LF). The subject is fixed translated text with CR/LF stripped and MIME-encoded. All headers are fixed by the code; no request data reaches a header. The body is plain text, UTF-8.
- **Survey title and info text** come from the survey's own translations, not from the invitation form. Both have control characters (`\p{Cc}`) and invisible formatting and direction marks (`\p{Cf}`) removed and spaces collapsed. The title is cut to 100 characters, the info text to `invitations.info_text_max_chars` (1000). Both are plain text; the system never turns text into links.
- Without a valid `mail.from`, nothing is sent: the function returns `false`, logs once per request, and the form says mail is not configured.
- Transports: `mail` (default) and `log`. `log` writes `.eml` files to `data/mail/` (mode 0600, written atomically) and logs a warning on every send. It is **for development only**, because the files contain usable links.
- `mail()` returning `true` means "handed to the local mail server", not "delivered". Operators must set up SPF and DKIM for the sender domain, or invitations may land in spam.

## 8. Audit log

**Table `audit_log`:** `id`, `actor_user_id` -> users (SET NULL), `action VARCHAR(50)`, `target_type VARCHAR(30)`, `target_id INT NULL`, `survey_id INT NULL` (no foreign key, so entries survive deleting the survey), `details JSON NULL`, timestamps.

- `details` holds IDs and fixed values only. Never passwords, tokens, nonces, hashes, IP addresses or email addresses (invitation events store the invitation ID). The helper rejects detail keys outside an allowlist.
- **Events:** login success; lockouts (`key_type` only); logout; session expiry; admin created or recovered (setup, CLI); admin flag granted or revoked; account disabled or enabled; survey created, status changed, answer mode set or locked; editor assigned or removed; participant added or removed; invitation created, sent, revoked or used; export run; password reset requested or completed.
- **Not logged:** individual login failures (noise, and an unknown email cannot be recorded without storing it), and answer events of any survey.
- **Visibility:** admins see everything. Editors see entries whose `survey_id` is a survey they edit (checked against `survey_members`). Participants and visitors see nothing.
- **Retention:** `audit.retention_days` (proposed 365, configurable), purged on about 1 in 100 requests.
- **Hooks into existing code** (`attempt_login()`, `record_failure()`, `logout_user()`, `session_begin()`, the setup page, the CLI) belong to the audit PR, with their own tests. A database error while writing an audit entry must never block a login or session start; fall back to `error_log`.

## 9. Index plan

To be checked with `EXPLAIN` on tables filled with a few thousand rows (on empty tables MySQL may scan instead). Drop any index that is not used.

| Table | Index | Query it serves |
|---|---|---|
| `survey_members` | PK `(survey_id, user_id)` | Is this person a member, and with which role? Members of a survey |
| `survey_members` | `KEY (user_id)` | "My surveys"; CASCADE when an account is deleted |
| `invitation_sends` | `KEY (sent_by, sent_at)` | Editor limits (hour, day) |
| `invitation_sends` | `KEY (sent_at)` | Purge of sends older than 24 h |
| `invitations` | `UNIQUE (token_hash)` | Opening a link |
| `invitations` | `KEY (expires_at)`, `KEY (used_at)`, `KEY (revoked_at)` | Purge queries |
| `invitations` | open-marker unique key, plus `(survey_id, email)` | Refresh check; invitation list per survey |
| `invitation_nonces` | `UNIQUE (nonce_hash)` | Session lookup |
| `invitation_nonces` | `KEY (invitation_id, created_at)` | The cap of 10; CASCADE |
| `invitation_nonces` | `KEY (expires_at)` | Purge |
| `audit_log` | `KEY (target_type, target_id, created_at)` | History of one item |
| `audit_log` | `KEY (survey_id, created_at)` | Editor view |
| `audit_log` | `KEY (created_at)` | Admin list and retention purge |

## 10. Security considerations

| Risk | Handling |
|---|---|
| Mail header injection | Addresses validated (ASCII, no CR/LF); subject fixed and MIME-encoded; title and info text only in the body, cleaned and length-limited; no request data in headers |
| Phishing through the survey info text | Plain text only, length limit, no links created by the system, fixed text around it, sending limited and audited. Editors are trusted staff, so a residual risk remains. |
| Invitation abuse | Server-side check of the editor role per survey; limits per editor (hour, day); at most 5 sends per invitation with a 10-minute cooldown; no invitations to closed surveys; every send audited |
| Token leakage | Only the hash is stored; the session holds invitation ID and nonce only; the token leaves the URL after the first request; `Referrer-Policy: no-referrer`; tokens never appear in logs or audit; unknown tokens are throttled per IP |
| Forwarded invitation | Whoever opens the link can register that address. Mitigations: short expiry, single use, revocation. This is the same trust model as any email magic link. |
| Registration with the wrong address | The address comes from the invitation, never from the request; a logged-in account must match it byte for byte |
| Privilege escalation | The admin flag is set only by admins (audited), never from a request; membership roles are set by code; editors can add participants only; the flag and memberships are reloaded on every request |
| Enumeration | One message for every login failure and every invalid token; the editor sees "invited" for every participant until first access; the response to adding a participant is identical in all cases |
| Secret survey link leaks | Long random slug, `Referrer-Policy: no-referrer`, `noindex`, not in the sitemap. Anyone who has the link can answer. |
| Account takeover through reset | Password reset by email is its own PR: hashed single-use token, same message whether or not the account exists |

## 11. Configuration keys

| Key | Proposed default |
|---|---|
| `mail.transport` | `mail` (`mail` or `log`) |
| `mail.from` | `null` (required) |
| `mail.from_name` | empty |
| `mail.envelope_from` | `null` (optional) |
| `invitations.expiry_days` | 7 |
| `invitations.max_sends` | 5 |
| `invitations.resend_cooldown_minutes` | 10 |
| `invitations.per_editor_hour` | 20 |
| `invitations.per_editor_day` | 100 |
| `invitations.retention_days` | 30 |
| `invitations.nonce_minutes` | 30 |
| `invitations.max_nonces` | 10 |
| `invitations.info_text_max_chars` | 1000 |
| `results.min_group_size` | 5 (anonymous surveys) |
| `audit.retention_days` | 365 |

All values must be positive integers; invalid values are logged and replaced by the default (same rule as the `auth.*` keys).

## 12. Data protection review points

These need an answer from the people responsible for data protection before the participant features go live.

1. **Anonymous surveys.** The system holds no account link for them. What remains: exact timestamps against server logs (decision 4), small groups reached through a secret link, and the fact that duplicate submissions cannot be prevented. Server access logs and database backups also contain times and IP addresses and should be named in the review, with their retention.
2. **Identified surveys.** Answers are personal data. Needed: retention, self-deletion of accounts, and what happens to identified answers when an account is deleted or a participant is removed from a survey (decided in the answers PR).
3. **No default answer mode.** The proposal has none, so editors choose actively.
4. **Invitation emails** contain personal data (the address, the survey title and info text) and pass through the host's mail system.
5. **Retention** of audit entries (365 days proposed) and invitations (30 days).
6. **Hashed IPs** are pseudonymised, not anonymous; the documentation states the 24-hour retention.

## 13. Pull request sequence

| PR | Content | Depends on |
|---|---|---|
| (a) | Replace `role` in `users` by `is_admin`: column, `CHECK` removed, helpers (`require_role()` becomes an admin check), setup and CLI create admins, tests, comments. A new commit on `feature/auth-core`, or migration 0003 after the merge (decision 3) | none |
| (b) | Login, logout, setup page, dashboard (admins only at this stage) | (a) |
| (c) | CI tests, documentation updates | (b) |
| (c2) | `principles.md` #19 and the `architecture.md` changes of section 4 | decisions 1 and 2 |
| (j) | Audit log, including the hooks into login, logout, setup and CLI | (b) |
| (d) | Account management (admin flag, disabling, last-admin protection) | (j) |
| (e) | Survey core: surveys, answer mode, `survey_members`, editor checks, preview mode | (j) |
| (f) | Mail layer | none |
| (i) | Password reset by email (before go-live) | (f) |
| (g) | Adding participants, invitations, registration, purge (step 0 first) | (e), (f), (j) |
| (h) | Taking surveys (anonymous and identified), results and exports, deletion behaviour | (g) |

## 14. Not covered here

- The **question and answer data model** for registry-style surveys (repeat indices, answer status such as not shown / skipped / unknown, versioned questions, provenance for migrated data). It affects tables that do not exist yet and should be decided before the survey PRs.
- Logo, front page and settings editing by admins. Proposed: logo upload limited to PNG, JPEG or WebP (no SVG uploads, which can contain scripts), stored in `uploads/`.
- Changing an account's email address, per-site access, second factor, and a change history for individual answers.

## 15. Open questions

1. **Who may create surveys?** Proposed: admins create surveys and assign editors; editors manage their surveys and participants. Alternative: any account with permission to create surveys.
2. **One role per survey.** Proposed: a person is editor or participant of a survey, not both. Editors preview.
3. **Registration only through invitation**, or also public self-registration for surveys that require login?
4. **Survey info text.** Proposed: the survey's own description. Alternative: a separate invitation text field. Should the email name the editor who added the person?
5. **`audit.retention_days`** (365 proposed) and whether study rules require longer.
6. **Minimum PHP version.** 8.0 has had no security support since November 2023. Raise the minimum, or keep it as a compatibility floor only?
7. **Who configures `mail.from` per instance**, and is a sender address on the host's own domain available?
8. **Minimum group size** for anonymous results (5 proposed): does it apply, now that anonymous surveys have no accounts?
