# Survey roles, member administration and participant access (proposal)

**Status:** proposal for review, updated after the maintainers' feedback and the member administration discussion. Nothing in this document is implemented. Where the text says "proposed", it can be accepted or changed; "open question" marks something that still needs an answer.

**Author / date:** Stebin Thomas, Nico Bekaan / 12.11.2026

## 1. Summary

SurveyScopes keeps people in a **member administration** that is separate from registration and matched to it by email address.

- A **member** is a person record: email address, optional name, admin flag, status. Members can be added to the member administration at any time, even if the person has not registered yet and is not connected to any survey.
- When a person registers, the email address is matched with the member record. The **admin flag, the status and the survey roles** stored on the member then apply to the new account.
- An **account** holds only the login credentials. The admin flag lives on the member. Everything else is a **role per survey**: *editor* or *participant*.
- A **participant** is a person who logs in and can see only the surveys they were added to.
- Participants are **added by an editor of a survey** when that survey requires a login. The editor finds or creates the member in member list and assigns the role. The person then receives an email with the survey info text:
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
| Take the survey | identified surveys, preview only | identified surveys, preview only | identified surveys | anonymous surveys (public or secret link) |
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
- `responses.member_id` (open question 5), status draft or submitted; answers can be resumed and (proposed) edited until the survey closes; save each input field while editing directly.
- Never listed publicly: a database check requires `answer_mode = 'anonymous' OR is_public = 0`.
- Answers are personal data, so the notification email says that the answers are linked to the participant.

## 6. Member administration and registration

### Concepts

| Term | Meaning |
|---|---|
| **Member** | A person record in the member administration: e.g., email address, name, admin flag, status. It can exist without an account and without any survey. |
| **Account** | Login credentials (password), created at registration and linked to exactly one member. |
| **Survey role** | Assignment of a member to one survey as *editor* or *participant*. It belongs to the member, so it can be set before the person registers. |

### Tables (sketch, not final SQL)
to be derived

### Member administration pages

- **Admins:** list members (status: not registered, registered, disabled); add, edit (name, admin flag, status), disable and delete members; assign survey roles; see pending admin records (flag set, no account).
- **Editors:** add participants to **their own surveys** only (section 7), and see only the members of those surveys. They never see the admin flag, other surveys of a member, or whether an address already has an account: the list shows "invited" until the person first opens the survey.
- **Not in v1:** CSV import of members, and email change by members if connected - changing in both tables.

### Registration and matching

Registration is the point where an account is linked to a member. It always requires **proof of mailbox control**: without it, anyone could enter an admin's address and receive the admin flag.

There are two ways in, and both end in the same token mechanism:

- **From a notification email:** the email contains a registration link with a token for the member (valid for 24 h).
- **From the registration page:** the person enters their address. The answer is always the same ("If this address can register, we have sent an email"). If a member with that address exists, is active and has no account, a token is created (within the limits) and mailed.

... other details are comming after next code commit and check

## 16. Open questions

1. **Who may create surveys?** any account with permission to create surveys --> please add a second flag create_surveys_allowed (admin automatically have)
2. **One role per survey.** not needing to decide - editor can also take the survey but have also the option to preview
3. **Registration:** open because one registration for all - also for participants and participants dont need to be in members table, they create their own record if necessary in memebers list. but if system is finding the person by name match it suggest using that email address and reregister
4. **Survey info text.** Proposed: the survey's own description.
5. **Identified answers** reference the member (proposed, so they survive deleting an account) , see above
6. **Display name:** is it needed on members, full name, everyone can see
7. **Retention of members who never register** Not now
8. **Minimum PHP version.** 8.0 has had no security support since November 2023. Raise the minimum, or keep it as a compatibility floor only?
9. **Who configures `mail.from` per instance**, admins can configure and check if available
10. **Minimum group size** for anonymous results (5 proposed): not now to define
