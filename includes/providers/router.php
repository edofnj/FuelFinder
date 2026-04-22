<?php
// Router paese: bbox semplici per rilevare provider.
// Aggiungere qui nuovi paesi man mano che si implementano provider.

function detectCountry($lat, $lon) {
    // Italia (incluse isole maggiori)
    if ($lat >= 35.4 && $lat <= 47.2 && $lon >= 6.5 && $lon <= 18.6) return 'IT';
    // Germania
    if ($lat >= 47.2 && $lat <= 55.1 && $lon >= 5.8 && $lon <= 15.1) return 'DE';
    return null;
}

// Ritorna lista paesi da interrogare dato centro + raggio (cross-border).
// Se il cerchio di ricerca interseca il bbox di un altro paese, includilo.
function countriesInRange($lat, $lon, $radiusKm) {
    $countries = [];
    $main = detectCountry($lat, $lon);
    if ($main) $countries[] = $main;

    // ~1° lat = 111km; ~1° lon = 111*cos(lat) km
    $dLat = $radiusKm / 111.0;
    $dLon = $radiusKm / (111.0 * max(cos(deg2rad($lat)), 0.1));

    $bboxes = [
        'IT' => [35.4, 47.2, 6.5, 18.6],
        'DE' => [47.2, 55.1, 5.8, 15.1],
    ];

    foreach ($bboxes as $cc => $b) {
        if (in_array($cc, $countries, true)) continue;
        // Intersezione bbox utente (lat±dLat, lon±dLon) con bbox paese
        if ($lat + $dLat < $b[0] || $lat - $dLat > $b[1]) continue;
        if ($lon + $dLon < $b[2] || $lon - $dLon > $b[3]) continue;
        $countries[] = $cc;
    }
    return $countries;
}

// Dispatcher: chiama provider giusto per paese.
function searchByCountry($country, $lat, $lon, $radiusKm, $fuelType) {
    switch ($country) {
        case 'IT': return mimitSearch($lat, $lon, $radiusKm, $fuelType);
        case 'DE': return tankerkoenigSearch($lat, $lon, $radiusKm, $fuelType);
        default:   return [];
    }
}

// Carburanti supportati per paese (per UI)
function supportedFuels($country) {
    $map = [
        'IT' => ['benzina', 'gasolio', 'gpl', 'metano'],
        'DE' => ['benzina', 'gasolio'], // e5, diesel
    ];
    return $map[$country] ?? ['benzina', 'gasolio'];
}
