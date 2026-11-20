<?php

declare(strict_types=1);

return [
    '_name' => 'English',

    '_og_locale' => 'en_US',

    'front.title' => 'Online surveys',
    'front.headline' => 'Surveys – simple, open and privacy-friendly',
    'front.intro' => 'SurveyScopes is an open-source platform for creating and conducting online surveys. No tracking, no third-party services – your data stays on this server.',
    'front.cta' => 'Browse public surveys',

    'surveys.title' => 'Public surveys',
    'surveys.empty' => 'There are no public surveys at the moment.',
    'surveys.lead' => 'Surveys that are currently open to everyone.',

    'feature.open.title' => 'Open source',
    'feature.open.text' => 'Transparent code under the MIT License.',
    'feature.privacy.title' => 'Privacy first',
    'feature.privacy.text' => 'No cookies for participants, no tracking, no external resources.',
    'feature.languages.title' => 'Multilingual',
    'feature.languages.text' => 'Surveys and pages in several languages.',

    'imprint.title' => 'Imprint',
    'privacy.title' => 'Privacy policy',
    'legal.missing' => 'This content has not been provided yet.',

    'footer.language' => 'Language',
    'footer.language_go' => 'Go',
    'front.features' => 'Features',
    'footer.powered' => 'Powered by SurveyScopes',
    'footer.login' => 'Sign in',

    '400.title' => 'Request could not be verified',
    '400.text' => 'The form has expired or was not sent from this site. Please go back, reload the page and try again.',
    '403.title' => 'Access denied',
    '403.text' => 'Your account is not allowed to open this page.',

    'login.title' => 'Sign in',
    'login.email' => 'E-mail address',
    'login.password' => 'Password',
    'login.submit' => 'Sign in',
    'login.failed' => 'E-mail address or password is incorrect.',
    'login.throttled' => 'Too many attempts. Please wait a few minutes and try again.',
    'login.unavailable' => 'Signing in is not available on this server yet.',
    'login.expired' => 'Your session has expired. Please sign in again.',
    'login.no_admin_access' => 'You are signed in. Your account has no access to the administration yet.',

    'logout.title' => 'Sign out',
    'logout.confirm' => 'Do you want to sign out?',
    'logout.submit' => 'Sign out',
    'logout.done' => 'You have been signed out.',

    'setup.title' => 'Set up the administrator account',
    'setup.intro' => 'Enter the token from the file data/setup-token, an e-mail address and a password (12 characters to 72 bytes). If the address already exists, it becomes an administrator and gets the new password. The token file is deleted when this succeeds.',
    'setup.token' => 'Setup token',
    'setup.email' => 'E-mail address',
    'setup.password' => 'New password',
    'setup.password_repeat' => 'Repeat password',
    'setup.submit' => 'Create account',
    'setup.failed' => 'The setup token is not correct.',
    'setup.throttled' => 'Too many attempts. Please wait a few minutes and try again.',
    'setup.unavailable' => 'Setup is not available: no database or app_key is configured.',
    'setup.invalid_email' => 'Please enter a valid e-mail address (ASCII characters only).',
    'setup.mismatch' => 'The two passwords do not match.',
    'setup.too_short' => 'The password must have at least 12 characters.',
    'setup.too_long' => 'The password must not be longer than 72 bytes.',
    'setup.invalid' => 'The password contains invalid characters.',
    'setup.not_writable' => 'The directory data/ is not writable, so the setup token could not be removed.',
    'setup.delete_failed' => 'The setup token could not be deleted (or was already used). Nothing was changed.',
    'setup.done' => 'The administrator account is ready. You can sign in now.',

    'admin.title' => 'Dashboard',
    'admin.signed_in_as' => 'Signed in as {email}',
    'admin.account' => 'Your account',
    'admin.status' => 'Status',
    'admin.status.active' => 'active',
    'admin.status.disabled' => 'disabled',
    'admin.is_admin' => 'Administrator',
    'admin.can_create_surveys' => 'May create surveys',
    'admin.yes' => 'yes',
    'admin.no' => 'no',
    'admin.survey_roles' => 'Survey roles',
    'admin.survey_roles_none' => 'None yet. Roles per survey (editor, participant) come with the survey module.',

    '404.title' => 'Page not found',
    '404.text' => 'The page you are looking for does not exist or has been moved.',
    '404.back' => 'Back to the home page',

    'error.title' => 'Temporarily unavailable',
    'error.text' => 'Something went wrong. Please try again in a few minutes.',

    'maintenance.title' => 'Maintenance',
    'maintenance.text' => 'This site is being updated and will be back in a moment.',
];
