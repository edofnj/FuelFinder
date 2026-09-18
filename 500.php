<?php
require __DIR__ . '/includes/error_page.php';
renderErrorPage(500, [
    'it' => [
        'title' => 'Qualcosa è andato storto',
        'sub'   => 'Si è verificato un errore sul server. Stiamo già rifornendo i motori — riprova tra poco.',
    ],
    'de' => [
        'title' => 'Etwas ist schiefgelaufen',
        'sub'   => 'Auf dem Server ist ein Fehler aufgetreten. Wir füllen schon nach — bitte versuche es gleich erneut.',
    ],
]);
