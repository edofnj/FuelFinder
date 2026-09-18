<?php
require __DIR__ . '/includes/error_page.php';
renderErrorPage(404, [
    'it' => [
        'title' => 'Pagina non trovata',
        'sub'   => 'La pagina che cerchi non esiste o è stata spostata. Il distributore più conveniente, però, è ancora là fuori.',
    ],
    'de' => [
        'title' => 'Seite nicht gefunden',
        'sub'   => 'Die gesuchte Seite existiert nicht oder wurde verschoben. Die günstigste Tankstelle wartet aber weiterhin auf dich.',
    ],
]);
