<?php
require __DIR__ . '/includes/error_page.php';
renderErrorPage(403, [
    'it' => [
        'title' => 'Accesso negato',
        'sub'   => 'Non hai i permessi per accedere a questa pagina.',
    ],
    'de' => [
        'title' => 'Zugriff verweigert',
        'sub'   => 'Du hast keine Berechtigung, diese Seite aufzurufen.',
    ],
]);
