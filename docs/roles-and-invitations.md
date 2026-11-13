# Survey roles, member administration and participant access (proposal)

**Status:** proposal for review, updated after the maintainers' feedback and the member administration discussion. Nothing in this document is implemented. Where the text says "proposed", it can be accepted or changed; "open question" marks something that still needs an answer.

**Author / date:** Stebin Thomas, Nico Bekaan / 12.11.2026

## 1. Summary

SurveyScopes keeps people in a **member administration** that is separate from registration and matched to it by email address.

- A **member** is a person record: email address, optional name, admin flag, status. Members can be added to the member administration at any time, even if the person has not registered yet and is not connected to any survey.
- When a person registers, the email address is matched with the member record. The **admin flag, the status and the survey roles** stored on the member then apply to the new account.
- An **account** holds only the login credentials. The admin flag lives on the member. Everything else is a **role per survey**: *editor* or *participant*.
- A **participant** is a person who logs in and can see only the surveys they were added to.
- Participants are **added by an editor of a survey** when that survey requires a login. The editor finds or creates the member by email address and assigns the role. The person then receives an email with the survey info text:
  - a member **without an account** gets a **registration link** and is told which email address it is connected to; registering with that address gives access to the survey;
  - a member who is **already registered** gets a link to the survey.
- Each survey has an **answer mode**:
  - **anonymous**: no login; reachable through a **public link that is also listed on the website**, or through a **secret link**;
  - **identified**: login required; answers are linked to the participant.
- A small **mail layer** using PHP `mail()`.
- An **audit log**.
- A sequence of small pull requests.

All code stays within the existing rules: PHP 8.0 compatible, no Composer packages, no new JavaScript requirements, SQL only in `src/repositories/`, CSRF on every POST, PRG with 303.

## 2. Decisions

| # | Topic | Status |
|---|---|---|
| 1 | **Principle 19.** No tracking, no analytics, no third-party requests; technically necessary cookies (for example for login) are allowed. | Decided by the maintainers. New wording in section 4. |
| 2 | **Roles.** `participant` is *not* added to a global role list. The admin flag is global; editor and participant are roles per survey. | Decided; change proposal for `architecture.md` section 6 in section 4. |
| 3 | **PR (a).** Introduce the member administration: a `members` table (email, admin flag, status), accounts linked to members by `member_id`, and the auth core reading the admin flag and status from the member. This replaces the `role` column. | Open: a new commit on `feature/auth-core`, or a migration 0003 after the merge (section 14). |
| 4 | **Timestamps on anonymous responses.** | Open: exact (no deviation) or date only (section 5). Proposed: date only. |
| 5 | **ASCII-only email addresses**, `utf8mb4_bin` collation for email columns. | Decided: yes. |
| 6 | **Mail.** PHP `mail()` as the first transport. | Decided: yes. |
| 7 | **Member administration** separate from registration, matched by email address. | Requested by the maintainers. The details in section 6 are proposed. |

## 3. Roles and permissions

There are three kinds of access: the global **admin flag** (on the member), a **role per survey** (editor or participant), and **visitors without an account** (anonymous surveys only).

| Capability | Admin (flag) | Editor of that survey | Participant of that survey | Visitor, no account |
|---|---|---|---|---|
| Site settings, logo, front page, imprint, privacy | yes | no | no | no |
| Manage members, the admin flag and status | yes | no | no | no |
| See the member list | all members | only members of their surveys | no | no |
| Create surveys (open question 1) | yes | no | no | no |
| Assign editors to a survey | yes | no | no | no |
| Edit the survey, questions, translations | all surveys | yes | no | no |
| Add and remove participants | all surveys | yes | no | no |
| View results and exports | all surveys | yes | no | no |
| See audit entries | all | entries about that survey | no | no |
| Preview (stores nothing) | yes | yes | no | no |
| Take the survey | preview only | preview only | identified surveys | anonymous surveys (public or secret link) |
| Admin area | all pages | survey pages of their surveys | no, "my surveys" page only | no |

- A person has at most one role per survey. They can be editor in one survey and participant in another.
- The last active admin cannot be demoted, disabled or deleted. "Admin" here means an active member with the admin flag **who has an account**; a pending admin record without an account does not count. This is checked in a transaction with `SELECT ... FOR UPDATE`. Setup and the CLI remain as recovery paths.
- The admin flag is set only by an admin (or by setup and the CLI). A request field never sets it.
- Access to a specific survey is always a server-side check on the survey membership, never a rank comparison.
- Landing page after login: admins go to the dashboard, people with editor roles to their survey list, participants to "my surveys".

## 4. Changes to existing principles and architecture

Proposed text for `principles.md` #19:

> No tracking, no analytics, no third-party requests. Only technically necessary cookies are used (for example the session cookie for logged-in users). Visitors of anonymous surveys get no cookie.

Proposed changes to `architecture.md`:

| Section | Today | Proposed |
|---|---|---|
| §2 | bootstrap starts the session | The session is lazy: it starts only on login, logout, setup, registration and account pages, and is stored in `data/sessions/`. Public pages never set a cookie. |
| §6 `users` | e-mail, password_hash, role (admin, editor), status | Accounts only: **member_id** (unique), password_hash, last_login_at. Email, status and the admin flag move to `members`. |
| §6 new `members` | none | **`members`**: email (normalised, `utf8mb4_bin`, ASCII only, unique), optional name, **is_admin** flag, status, claimed_at. |
| §6 `surveys` | slug, status, is_public, default_locale, opens/closes at, owner | slug, status, is_public, default_locale, opens/closes at, **created_by**, **answer_mode**, answer_mode_locked_at. The single owner is replaced by editor roles in `survey_members`. |
| §6 new | none | **`survey_members`**: which member has which role in which survey. |
| §6 new | none | `registration_tokens`, `registration_nonces`, `invitation_sends`, `audit_log` (sections 6, 7 and 9). |
| §6 `responses` | optional anonymous token | Identified mode: `member_id` (open question 5), draft or submitted. Anonymous mode: no person reference, random id, date-only timestamps if decision 4 is accepted. |
| §6 timestamps | exact `created_at` / `updated_at` everywhere | Exception for responses of anonymous surveys (decision 4). |
| §6 `login_attempts` | not listed | Added. No foreign key on purpose, because it also throttles addresses that do not exist. |
| §10 | no cookies besides the session cookie | Consistent with the new principle 19. Adds throttling keys, password length (12 characters to 72 bytes), idle and absolute session lifetime, hashed-IP retention. |
| §10 mail | none | PHP `mail()`; mail layer rules (section 8). |

## 5. Surveys and answer modes

The editor or admin **must choose** the mode when creating a survey (no default, no preselected option). It is locked at the first answer (`answer_mode_locked_at`); the repository changes it only `WHERE answer_mode_locked_at IS NULL`. The mode is shown on the first page of the survey and stated in the notification email.

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
- `responses.member_id` (open question 5), status draft or submitted; answers can be resumed and (proposed) edited until the survey closes.
- Never listed publicly: a database check requires `answer_mode = 'anonymous' OR is_public = 0`.
- Answers are personal data, so the notification email says that the answers are linked to the participant.

## 6. Member administration and registration

### Concepts

| Term | Meaning |
|---|---|
| **Member** | A person record in the member administration: email address, optional name, admin flag, status. It can exist without an account and without any survey. |
| **Account** | Login credentials (password), created at registration and linked to exactly one member. |
| **Survey role** | Assignment of a member to one survey as *editor* or *participant*. It belongs to the member, so it can be set before the person registers. |

### Tables (sketch, not final SQL)

**`members`**: `id`, `email VARCHAR(254)` (`utf8mb4_bin`, ASCII, normalised, UNIQUE), `display_name` NULL, `is_admin TINYINT(1) NOT NULL DEFAULT 0`, `status` (`active` or `disabled`), `created_by` -> members (SET NULL), `claimed_at` NULL (set when an account is linked), timestamps.

**`users`** (accounts): `id`, `member_id` -> members (UNIQUE, RESTRICT), `password_hash`, `last_login_at`, timestamps. Email, status and the admin flag live on the member, so there is one source of truth. The former columns `email`, `role` and `status` are removed.

**`registration_tokens`**: `id`, `member_id` -> members (CASCADE), `token_hash CHAR(64)` (SHA-256 of a 32-byte random token) UNIQUE, `expires_at`, `used_at`, timestamps. A member can have several open tokens (for example one per notification email); using one revokes all others. At most `registration.max_open_tokens` (5) are kept; the oldest is deleted when a new one is created. This needs no generated column or open marker.

**`registration_nonces`**: `id`, `token_id` -> registration_tokens (CASCADE), `nonce_hash CHAR(64)` UNIQUE, `expires_at`, timestamps. At most 10 per token (the oldest is deleted when an 11th is added). Several nonces are allowed because mail link scanners open the link before the person does.

### Member administration pages

- **Admins:** list members (status: not registered, registered, disabled); add, edit (name, admin flag, status), disable and delete members; assign survey roles; see pending admin records (flag set, no account).
- **Editors:** add participants to **their own surveys** only (section 7), and see only the members of those surveys. They never see the admin flag, other surveys of a member, or whether an address already has an account: the list shows "invited" until the person first opens the survey.
- **Not in v1:** CSV import of members, and email change by members.

### Registration and matching

Registration is the point where an account is linked to a member. It always requires **proof of mailbox control**: without it, anyone could enter an admin's address and receive the admin flag.

There are two ways in, and both end in the same token mechanism:

- **From a notification email:** the email contains a registration link with a token for the member.
- **From the registration page:** the person enters their address. The answer is always the same ("If this address can register, we have sent an email"). If a member with that address exists, is active and has no account, a token is created (within the limits) and mailed.

Flow:

1. `GET /<lang>/register/<token>`: an unknown, expired or used token gets one generic message and counts against the IP throttle. Headers: `Referrer-Policy: no-referrer`, `Cache-Control: no-store`, `X-Robots-Tag: noindex, nofollow`. If the token is valid, a nonce is created (its hash stored) and **only the token ID and the nonce** go into the session, never the token. Then a 303 to `/<lang>/register`, a URL without the token. Opening the link does not use it up.
2. `GET /<lang>/register` loads the token by ID and nonce hash. If the member now has an account, the page says so and points to login and password reset.
3. Otherwise the page shows the registration form. The address comes from the member record and is not an input field. The person sets a password (12 characters to 72 bytes, entered twice).
4. `POST`, in one transaction: CSRF check; password rules; an atomic `UPDATE registration_tokens SET used_at = ? WHERE id = ? AND used_at IS NULL AND expires_at > ?` that must change exactly one row; the member row is locked (`SELECT ... FOR UPDATE`) and must be active and without an account; the account is created with `member_id`; `claimed_at` is set; all other open tokens of the member and their nonces are deleted; an audit entry is written; `session_regenerate_id(true)`; 303 to the landing page.
5. From then on, the **admin flag, the status and the survey roles are read from the member record on every request**. They are not copied into the account, so an admin's change takes effect at once.

| Situation | Behaviour |
|---|---|
| Member exists, no account yet | Account is created and linked. |
| Member exists and already has an account | No second account. The person uses password reset. |
| No member record for the address | Proposed: registration is refused with the same generic response (closed registration, open question 3). |
| Member disabled | No email is sent and registration is refused, with the same response. |

### Admin flag and pending admins

- Only admins change the flag (audited). Setup and the CLI create the first admin member together with its account, in one transaction.
- Pending admin records (flag set, no account) are highlighted in the member list, so a stale flag does not stay unnoticed. Admins can revoke them.

### Purge

On about 1 in 100 registration requests, tokens that are more than `registration.token_retention_days` (30) past their expiry or use are deleted with their nonces; expired nonces are deleted on every registration request. Members who never registered are deleted after `members.unclaimed_retention_days` (365), together with their survey roles.

## 7. Adding participants to surveys (identified surveys)

### Tables (sketch, not final SQL)

**`survey_members`**: `survey_id` -> surveys (CASCADE), `member_id` -> members (CASCADE), `role` (`editor` or `participant`), `added_by` -> members (SET NULL), `notified_at` NULL, `joined_at` NULL (set at first access to the survey), timestamps. PK `(survey_id, member_id)`.

**`invitation_sends`**: `id`, `survey_id` -> surveys (SET NULL), `member_id` -> members (CASCADE, the recipient), `sent_by` -> members (SET NULL), `sent_at`. One row per email sent, as the basis for the sending limits.

### Adding a participant (editor or admin)

1. The server checks that the user is an admin or an editor of that survey, that the survey is in identified mode, and that it is not closed.
2. The address must pass the ASCII email check. The form states that non-ASCII addresses are not supported.
3. Limits are checked against `invitation_sends`: per editor (20 emails per hour, 100 per day) and per recipient (5 emails per 24 hours across all surveys). All are configurable.
4. The server looks up the normalised address in `members` and **creates the member if it does not exist**. Editors can create members this way, but cannot edit or see existing member records beyond this survey.
5. The `survey_members` row with role participant is added (if not already there).
6. The notification email is sent:
   - member **with an account**: a link to the survey; they must log in to open it;
   - member **without an account**: a new registration token is created and the email contains the registration link;
   - **disabled member**: nothing is sent.
7. The editor always sees the same message ("Participant added; an email was sent"). In the member list of the survey, everyone shows as "invited" until `joined_at` is set at their first access, so the editor cannot learn whether an address already has an account.

Removing a participant deletes the `survey_members` row and writes an audit entry. Their identified answers stay (proposed; final rule in the answers PR). Registration tokens are not tied to a survey, so they stay valid for registration.

### The emails

Plain text, UTF-8, language chosen from the survey's translations (default: the survey's default locale). Subject: fixed translated text with the site name. Body:

- fixed translated text;
- the survey title and the **survey info text** (the survey's description in that language), both cleaned as described in section 8;
- the statement of the answer mode ("your answers are linked to you");
- **member without an account:** the email address the invitation is connected to, the registration link and the expiry date;
- **member with an account:** a link to the survey;
- a line saying that the email can be ignored if it was not expected.

## 8. Mail layer

- `send_mail(string $to, string $subject, string $body): bool`. Used for notification emails, registration emails and, later, password reset.
- `$to` and `mail.from` must pass the ASCII email check (no CR/LF). The subject is fixed translated text with CR/LF stripped and MIME-encoded. All headers are fixed by the code; no request data reaches a header. The body is plain text, UTF-8.
- **Survey title and info text** come from the survey's own translations, not from a form field. Both have control characters (`\p{Cc}`) and invisible formatting and direction marks (`\p{Cf}`) removed and spaces collapsed. The title is cut to 100 characters, the info text to `invitations.info_text_max_chars` (1000). Both are plain text; the system never turns text into links.
- Without a valid `mail.from`, nothing is sent: the function returns `false`, logs once per request, and the form says mail is not configured.
- Transports: `mail` (default) and `log`. `log` writes `.eml` files to `data/mail/` (mode 0600, written atomically) and logs a warning on every send. It is **for development only**, because the files contain usable links.
- `mail()` returning `true` means "handed to the local mail server", not "delivered". Operators must set up SPF and DKIM for the sender domain, or emails may land in spam.

## 9. Audit log

**Table `audit_log`:** `id`, `actor_member_id` -> members (SET NULL), `action VARCHAR(50)`, `target_type VARCHAR(30)`, `target_id INT NULL`, `survey_id INT NULL` (no foreign key, so entries survive deleting the survey), `details JSON NULL`, timestamps.

- `details` holds IDs and fixed values only. Never passwords, tokens, nonces, hashes, IP addresses or email addresses (member events store the member ID). The helper rejects detail keys outside an allowlist.
- **Events:** login success; lockouts (`key_type` only); logout; session expiry; admin created or recovered (setup, CLI); member created, edited, disabled, enabled or deleted; admin flag granted or revoked; account linked to a member (registration); registration email sent; survey created, status changed, answer mode set or locked; editor assigned or removed; participant added or removed; export run; password reset requested or completed.
- **Not logged:** individual login failures (noise, and an unknown email cannot be recorded without storing it), and answer events of any survey.
- **Visibility:** admins see everything. Editors see entries whose `survey_id` is a survey they edit (checked against `survey_members`). Participants and visitors see nothing.
- **Retention:** `audit.retention_days` (proposed 365, configurable), purged on about 1 in 100 requests.
- **Hooks into existing code** (`attempt_login()`, `record_failure()`, `logout_user()`, `session_begin()`, the setup page, the CLI) belong to the audit PR, with their own tests. A database error while writing an audit entry must never block a login or session start; fall back to `error_log`.

## 10. Index plan

To be checked with `EXPLAIN` on tables filled with a few thousand rows (on empty tables MySQL may scan instead). Drop any index that is not used.

| Table | Index | Query it serves |
|---|---|---|
| `members` | `UNIQUE (email)` | Registration matching, adding a participant, login lookup |
| `members` | `KEY (is_admin, status)` | Last-admin check, pending admin list |
| `members` | `KEY (claimed_at, created_at)` | Purge of members who never registered |
| `users` | `UNIQUE (member_id)` | Login: account of a member |
| `survey_members` | PK `(survey_id, member_id)` | Is this member in the survey, and with which role? Members of a survey |
| `survey_members` | `KEY (member_id)` | "My surveys"; CASCADE when a member is deleted |
| `invitation_sends` | `KEY (sent_by, sent_at)` | Editor limits (hour, day) |
| `invitation_sends` | `KEY (member_id, sent_at)` | Per-recipient limit |
| `registration_tokens` | `UNIQUE (token_hash)` | Opening a link |
| `registration_tokens` | `KEY (member_id, created_at)` | The cap of 5 open tokens; CASCADE |
| `registration_tokens` | `KEY (expires_at)` | Purge |
| `registration_nonces` | `UNIQUE (nonce_hash)` | Session lookup |
| `registration_nonces` | `KEY (token_id, created_at)` | The cap of 10; CASCADE |
| `registration_nonces` | `KEY (expires_at)` | Purge |
| `audit_log` | `KEY (target_type, target_id, created_at)` | History of one item |
| `audit_log` | `KEY (survey_id, created_at)` | Editor view |
| `audit_log` | `KEY (created_at)` | Admin list and retention purge |

## 11. Security considerations

| Risk | Handling |
|---|---|
| Registering with someone else's address to receive its admin flag or roles | Matching happens only after the verification link of the mailed token is used; the address comes from the member record, never from a request field |
| Stale admin flag on an address that someone else now controls | Pending admin records are highlighted, revocable, and removed after the retention period |
| Enumeration of members | Uniform responses for the registration page and for adding a participant; editors see "invited" until first access; one message for every login failure and every invalid token |
| Forwarded registration link | Short expiry, single use, other tokens revoked at registration; the same trust model as any email magic link |
| Mail header injection | Addresses validated (ASCII, no CR/LF); subject fixed and MIME-encoded; title and info text only in the body, cleaned and length-limited; no request data in headers |
| Phishing through the survey info text | Plain text only, length limit, no links created by the system, fixed text around it, sending limited and audited. Editors are trusted staff, so a residual risk remains. |
| Email abuse | Server-side check of the editor role per survey; limits per editor and per recipient; no notifications for closed surveys; every send audited; unknown tokens throttled per IP |
| Token leakage | Only the hash is stored; the session holds token ID and nonce only; the token leaves the URL after the first request; `Referrer-Policy: no-referrer`; tokens never appear in logs or audit |
| Privilege escalation | The admin flag is set only by admins (audited), never from a request; survey roles are set by code and editors can add participants only; the flag, status and roles are reloaded from the member on every request |
| Disabled member keeps a session | The member status is reloaded on every request, so the session ends with the next request |
| Look-alike or duplicate addresses | `UNIQUE` on a `utf8mb4_bin` column, one shared normalisation function, ASCII-only addresses |
| Secret survey link leaks | Long random slug, `Referrer-Policy: no-referrer`, `noindex`, not in the sitemap. Anyone who has the link can answer. |
| Account takeover through reset | Password reset by email is part of the registration PR: hashed single-use token, same message whether or not the account exists |

## 12. Configuration keys

| Key | Proposed default |
|---|---|
| `mail.transport` | `mail` (`mail` or `log`) |
| `mail.from` | `null` (required) |
| `mail.from_name` | empty |
| `mail.envelope_from` | `null` (optional) |
| `registration.expiry_days` | 7 |
| `registration.max_open_tokens` | 5 |
| `registration.nonce_minutes` | 30 |
| `registration.max_nonces` | 10 |
| `registration.token_retention_days` | 30 |
| `invitations.per_editor_hour` | 20 |
| `invitations.per_editor_day` | 100 |
| `invitations.per_recipient_day` | 5 |
| `invitations.info_text_max_chars` | 1000 |
| `members.unclaimed_retention_days` | 365 |
| `results.min_group_size` | 5 (anonymous surveys) |
| `audit.retention_days` | 365 |

All values must be positive integers; invalid values are logged and replaced by the default (same rule as the `auth.*` keys).

## 13. Data protection review points

These need an answer from the people responsible for data protection before the participant features go live.

1. **Member records exist before the person registers.** They hold personal data (email, optional name). The notification email on the first survey assignment tells the person that a record exists; records that are never claimed are deleted after the retention period (365 days proposed).
2. **Anonymous surveys.** The system holds no account link for them. What remains: exact timestamps against server logs (decision 4), small groups reached through a secret link, and the fact that duplicate submissions cannot be prevented. Server access logs and database backups also contain times and IP addresses and should be named in the review, with their retention.
3. **Identified surveys.** Answers are personal data. Needed: retention, what happens to answers when a member is removed from a survey or deleted (decided in the answers PR), and a way to export or delete a member's data.
4. **No default answer mode.** The proposal has none, so editors choose actively.
5. **Emails** contain personal data (the address, the survey title and info text) and pass through the host's mail system.
6. **Retention** of audit entries (365 days proposed) and registration tokens (30 days after expiry or use).
7. **Hashed IPs** are pseudonymised, not anonymous; the documentation states the 24-hour retention.

## 14. Pull request sequence

| PR | Content | Depends on |
|---|---|---|
| (a) | Member administration foundation: `members` table, `users` linked by `member_id`, `role`, `email` and `status` removed from `users`, auth core reads the admin flag and status from the member, tests, comments. A new commit on `feature/auth-core`, or migration 0003 after the merge (decision 3). | none |
| (b) | Login, logout, setup page (creates the first admin member and its account), dashboard (admins only at this stage); `tools/create-admin.php` | (a) |
| (c) | CI tests, documentation updates | (b) |
| (c2) | `principles.md` #19 and the `architecture.md` changes of section 4 | decisions 1, 2 and 7 |
| (j) | Audit log, including the hooks into login, logout, setup and CLI | (b) |
| (d) | Member administration pages for admins; admin flag, disabling, last-admin protection, pending admins | (j) |
| (f) | Mail layer | none |
| (i) | Registration with verification link and matching; password reset | (f), (d) |
| (e) | Survey core: surveys, answer mode, `survey_members`, editor checks, preview mode | (j), (d) |
| (g) | Adding participants to surveys, notification emails, sending limits, purge | (e), (f), (i) |
| (h) | Taking surveys (anonymous and identified), results and exports, deletion behaviour | (g) |

## 15. Not covered here

- The **question and answer data model** for registry-style surveys (repeat indices, answer status such as not shown / skipped / unknown, versioned questions, provenance for migrated data). It affects tables that do not exist yet and should be decided before the survey PRs.
- Logo, front page and settings editing by admins. Proposed: logo upload limited to PNG, JPEG or WebP (no SVG uploads, which can contain scripts), stored in `uploads/`.
- Changing a member's email address (the new address would have to be verified, because matching uses the address), per-site access, second factor, and a change history for individual answers.

## 16. Open questions

1. **Who may create surveys?** Proposed: admins create surveys and assign editors; editors manage their surveys and participants. Alternative: any account with permission to create surveys.
2. **One role per survey.** Proposed: a person is editor or participant of a survey, not both. Editors preview.
3. **Registration:** closed (only addresses in the member administration may register; proposed) or open (anyone may register, with no roles until assigned)?
4. **Survey info text.** Proposed: the survey's own description. Alternative: a separate notification text field. Should the email name the editor who added the person?
5. **Identified answers** reference the member (proposed, so they survive deleting an account) or the account?
6. **Display name:** is it needed on members, and who may see it?
7. **Retention of members who never register** (365 days proposed) and of audit entries (365 days proposed); whether study rules require longer.
8. **Minimum PHP version.** 8.0 has had no security support since November 2023. Raise the minimum, or keep it as a compatibility floor only?
9. **Who configures `mail.from` per instance**, and is a sender address on the host's own domain available?
10. **Minimum group size** for anonymous results (5 proposed): does it apply, now that anonymous surveys have no accounts?
