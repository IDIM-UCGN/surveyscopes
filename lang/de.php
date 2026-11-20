<?php

declare(strict_types=1);

return [
    '_name' => 'Deutsch',

    '_og_locale' => 'de_DE',

    'front.title' => 'Online-Umfragen',
    'front.headline' => 'Umfragen – einfach, offen und datenschutzfreundlich',
    'front.intro' => 'SurveyScopes ist eine Open-Source-Plattform zum Erstellen und Durchführen von Online-Umfragen. Kein Tracking, keine Drittanbieter – Ihre Daten bleiben auf diesem Server.',
    'front.cta' => 'Öffentliche Umfragen ansehen',

    'surveys.title' => 'Öffentliche Umfragen',
    'surveys.empty' => 'Derzeit gibt es keine öffentlichen Umfragen.',
    'surveys.lead' => 'Umfragen, an denen aktuell alle teilnehmen können.',

    'feature.open.title' => 'Open Source',
    'feature.open.text' => 'Transparenter Code unter der MIT-Lizenz.',
    'feature.privacy.title' => 'Datenschutz zuerst',
    'feature.privacy.text' => 'Keine Cookies für Teilnehmende, kein Tracking, keine externen Ressourcen.',
    'feature.languages.title' => 'Mehrsprachig',
    'feature.languages.text' => 'Umfragen und Seiten in mehreren Sprachen.',

    'imprint.title' => 'Impressum',
    'privacy.title' => 'Datenschutz',
    'legal.missing' => 'Dieser Inhalt wurde noch nicht hinterlegt.',

    'footer.language' => 'Sprache',
    'footer.language_go' => 'Wechseln',
    'front.features' => 'Merkmale',
    'footer.powered' => 'Betrieben mit SurveyScopes',
    'footer.login' => 'Anmelden',

    '400.title' => 'Anfrage konnte nicht geprüft werden',
    '400.text' => 'Das Formular ist abgelaufen oder stammt nicht von dieser Seite. Bitte gehen Sie zurück, laden Sie die Seite neu und versuchen Sie es erneut.',
    '403.title' => 'Zugriff verweigert',
    '403.text' => 'Ihr Konto darf diese Seite nicht öffnen.',

    'login.title' => 'Anmelden',
    'login.email' => 'E-Mail-Adresse',
    'login.password' => 'Passwort',
    'login.submit' => 'Anmelden',
    'login.failed' => 'E-Mail-Adresse oder Passwort ist falsch.',
    'login.throttled' => 'Zu viele Versuche. Bitte warten Sie einige Minuten und versuchen Sie es erneut.',
    'login.unavailable' => 'Die Anmeldung ist auf diesem Server noch nicht verfügbar.',
    'login.expired' => 'Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.',
    'login.no_admin_access' => 'Sie sind angemeldet. Ihr Konto hat noch keinen Zugang zur Verwaltung.',

    'logout.title' => 'Abmelden',
    'logout.confirm' => 'Möchten Sie sich abmelden?',
    'logout.submit' => 'Abmelden',
    'logout.done' => 'Sie wurden abgemeldet.',

    'setup.title' => 'Administrator-Konto einrichten',
    'setup.intro' => 'Geben Sie das Token aus der Datei data/setup-token, eine E-Mail-Adresse und ein Passwort (12 Zeichen bis 72 Bytes) ein. Existiert die Adresse bereits, wird sie Administrator und erhält das neue Passwort. Die Token-Datei wird gelöscht, sobald dies gelingt.',
    'setup.token' => 'Setup-Token',
    'setup.email' => 'E-Mail-Adresse',
    'setup.password' => 'Neues Passwort',
    'setup.password_repeat' => 'Passwort wiederholen',
    'setup.submit' => 'Konto anlegen',
    'setup.failed' => 'Das Setup-Token ist nicht korrekt.',
    'setup.throttled' => 'Zu viele Versuche. Bitte warten Sie einige Minuten und versuchen Sie es erneut.',
    'setup.unavailable' => 'Das Setup ist nicht verfügbar: Es ist keine Datenbank oder kein app_key konfiguriert.',
    'setup.invalid_email' => 'Bitte geben Sie eine gültige E-Mail-Adresse ein (nur ASCII-Zeichen).',
    'setup.mismatch' => 'Die beiden Passwörter stimmen nicht überein.',
    'setup.too_short' => 'Das Passwort muss mindestens 12 Zeichen lang sein.',
    'setup.too_long' => 'Das Passwort darf höchstens 72 Bytes lang sein.',
    'setup.invalid' => 'Das Passwort enthält ungültige Zeichen.',
    'setup.not_writable' => 'Das Verzeichnis data/ ist nicht beschreibbar, daher konnte das Setup-Token nicht entfernt werden.',
    'setup.delete_failed' => 'Das Setup-Token konnte nicht gelöscht werden (oder wurde schon verwendet). Es wurde nichts geändert.',
    'setup.done' => 'Das Administrator-Konto ist eingerichtet. Sie können sich jetzt anmelden.',

    'admin.title' => 'Übersicht',
    'admin.signed_in_as' => 'Angemeldet als {email}',
    'admin.account' => 'Ihr Konto',
    'admin.status' => 'Status',
    'admin.status.active' => 'aktiv',
    'admin.status.disabled' => 'deaktiviert',
    'admin.is_admin' => 'Administrator',
    'admin.can_create_surveys' => 'Darf Umfragen erstellen',
    'admin.yes' => 'ja',
    'admin.no' => 'nein',
    'admin.survey_roles' => 'Rollen in Umfragen',
    'admin.survey_roles_none' => 'Noch keine. Rollen pro Umfrage (Editor, Teilnehmende) kommen mit dem Umfragemodul.',

    '404.title' => 'Seite nicht gefunden',
    '404.text' => 'Die gesuchte Seite existiert nicht oder wurde verschoben.',
    '404.back' => 'Zur Startseite',

    'error.title' => 'Vorübergehend nicht verfügbar',
    'error.text' => 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es in einigen Minuten erneut.',

    'maintenance.title' => 'Wartung',
    'maintenance.text' => 'Diese Seite wird gerade aktualisiert und ist gleich wieder erreichbar.',
];
