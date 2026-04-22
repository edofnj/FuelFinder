<?php
// Provider MIMIT (Italia). Ritorna stazioni in schema unificato.
// Schema: [id, brand, name, addr, lat, lon, price, fuelType, insertDate, country, isSelf]

function mimitSearch($lat, $lon, $radiusKm, $fuelType) {
    $fuelId = tipoToFuelId($fuelType);
    $payload = [
        'points'        => [['lat' => $lat, 'lng' => $lon]],
        'radius'        => $radiusKm,
        'fuelType'      => $fuelId,
        'refuelingMode' => 'x',
        'priceOrder'    => 'asc',
    ];

    $data = ospzPost('/search/zone', $payload);
    if (empty($data['results'])) return [];

    $anagrafica = caricaAnagrafica();
    $fuelIdInt  = (int)$fuelId;
    $out        = [];

    foreach ($data['results'] as $item) {
        $brand = trim($item['brand'] ?? $item['name'] ?? '');

        // Prezzo per fuelId richiesto, preferisce self
        $prezzo = null; $isSelf = false;
        foreach ($item['fuels'] as $fuel) {
            if ((int)($fuel['fuelId'] ?? 0) !== $fuelIdInt) continue;
            if ($prezzo === null || ($fuel['isSelf'] && !$isSelf)) {
                $prezzo = (float)$fuel['price'];
                $isSelf = (bool)$fuel['isSelf'];
            }
        }
        if ($prezzo === null) continue;

        // Scarta prezzi > 3 giorni
        $insertDate = $item['insertDate'] ?? '';
        if ($insertDate) {
            $ts = strtotime($insertDate);
            if ($ts && (time() - $ts) > 86400 * 3) continue;
        }

        $impId = (int)($item['id'] ?? 0);
        if ($impId && isset($anagrafica[$impId])) {
            $name = $anagrafica[$impId]['nome'];
            $addr = $anagrafica[$impId]['addr'];
        } else {
            $name = $item['name'] ?? $brand;
            $addr = !empty($item['address']) ? $item['address'] : 'Vedi mappa';
        }

        $out[] = [
            'id'         => $impId,
            'brand'      => $brand,
            'name'       => $name,
            'addr'       => $addr,
            'lat'        => (float)($item['location']['lat'] ?? 0),
            'lon'        => (float)($item['location']['lng'] ?? 0),
            'price'      => $prezzo,
            'fuelType'   => $fuelType,
            'insertDate' => $insertDate,
            'country'    => 'IT',
            'isSelf'     => $isSelf,
            'distanceApi'=> isset($item['distance']) ? (float)$item['distance'] : null,
        ];
    }
    return $out;
}
