<?php
require 'includes/config.php';
require 'includes/i18n.php';
require 'includes/cache.php';
require 'includes/api.php';
require 'includes/route_data.php';

// Restore form values after POST
$valTipo           = $_POST['tipo']           ?? 'benzina';
$valConsumo        = $_POST['consumo']        ?? '';
$valCorridor       = $_POST['corridor']       ?? '5';
$valMaxKm          = $_POST['max_km']         ?? '0';
$valAvoidMotorway  = isset($_POST['avoid_motorway']);
$valAvoidToll      = isset($_POST['avoid_toll']);
$valFromLat   = $_POST['from_lat']   ?? '';
$valFromLon   = $_POST['from_lon']   ?? '';
$valToLat     = $_POST['to_lat']     ?? '';
$valToLon     = $_POST['to_lon']     ?? '';
$valFromLabel = $_POST['from_label'] ?? '';
$valToLabel   = $_POST['to_label']   ?? '';

$routeResult   = null;
$routeStations = [];
$routeError    = null;
$hasResults    = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['calc_route'])) {
    $fromLat    = (float)$valFromLat;
    $fromLon    = (float)$valFromLon;
    $toLat      = (float)$valToLat;
    $toLon      = (float)$valToLon;
    $corridorKm = (float)$valCorridor ?: 5.0;
    $consumo    = (float)$valConsumo  ?: 7.0;
    $fuelType   = $valTipo;
    $maxKm      = (float)$valMaxKm;

    if ($fromLat == 0.0 || $toLat == 0.0) {
        $routeError = t('route_err_coords');
    } else {
        $exclude = [];
        if ($valAvoidMotorway) $exclude[] = 'motorway';
        if ($valAvoidToll)     $exclude[] = 'toll';
        $osrmRoute = getRoute($fromLat, $fromLon, $toLat, $toLon, $exclude);
        if (!$osrmRoute) {
            $routeError = t('route_err_noroute');
        } else {
            $routeResult  = $osrmRoute;
            $coords       = $osrmRoute['coords'];
            $searchRadius = max($corridorKm / 2.0 + 3.0, 8.0);
            $waypoints    = sampleRouteWaypoints($coords, 8.0);

            if (count($waypoints) > 60) {
                $step = (int)ceil(count($waypoints) / 60);
                $tmp  = [];
                foreach ($waypoints as $k => $wp) { if ($k % $step === 0) $tmp[] = $wp; }
                $waypoints = $tmp;
            }

            $itWps = []; $deWps = [];
            foreach ($waypoints as $wp) {
                if (routeDetectCountry($wp['lat'], $wp['lon']) === 'DE') $deWps[] = $wp;
                else $itWps[] = $wp;
            }

            $rawStations = [];
            if (!empty($itWps)) $rawStations = array_merge($rawStations, routeSearchMimit($itWps, $searchRadius, $fuelType));
            if (!empty($deWps)) $rawStations = array_merge($rawStations, routeSearchTK($deWps, min($searchRadius, 25.0), $fuelType));

            $processed = [];
            foreach ($rawStations as $s) {
                $info = stationRouteInfo($s['lat'], $s['lon'], $coords);
                if ($info['perp_dist'] * 2 > $corridorKm) continue;
                if ($maxKm > 0 && $info['km_along'] > $maxKm) continue;
                $s['perp_dist'] = $info['perp_dist'];
                $s['km_along']  = $info['km_along'];
                $s['detour_km'] = round($info['perp_dist'] * 2, 2);
                $processed[] = $s;
            }

            if (empty($processed)) {
                $routeError = t('route_err_nostations');
            } else {
                // Reference: cheapest on-route station. If none, cheapest overall.
                $onRouteStations = array_values(array_filter($processed, fn($s) => $s['detour_km'] <= 0.1));
                $hasOnRoute = !empty($onRouteStations);
                $refPrice = $hasOnRoute
                    ? min(array_map(fn($s) => $s['price'], $onRouteStations))
                    : min(array_map(fn($s) => $s['price'], $processed));

                foreach ($processed as &$s) {
                    $detour     = $s['detour_km'];
                    $detourFuel = $detour * $consumo / 100.0;
                    $priceDiff  = $refPrice - $s['price'];  // >0 = this station cheaper than reference

                    if ($detour <= 0.1) {
                        $s['break_even'] = 0;
                    } elseif (!$hasOnRoute && abs($priceDiff) <= 0.0001) {
                        $s['break_even'] = -1;  // cheapest available, no on-route reference
                    } elseif ($priceDiff <= 0.0001) {
                        $s['break_even'] = null;  // off-route AND not cheaper than on-route ref
                    } else {
                        $s['break_even'] = (int)ceil(($detourFuel * $s['price']) / $priceDiff);
                    }
                    $s['effective_price'] = $s['price'] + ($detourFuel * $s['price']) / 50.0;
                }
                unset($s);

                usort($processed, fn($a, $b) => $a['effective_price'] <=> $b['effective_price']);
                $routeStations = $processed;
                $hasResults    = true;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= currentLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>FuelFinder — <?= t('nav_route') ?></title>
<link rel="icon" type="image/svg+xml" href="img/logo.svg">
<link rel="icon" type="image/png" sizes="32x32" href="img/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="img/favicon-16.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0d0d1a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="img/apple-touch-icon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body>

<div class="loading-overlay" id="loadingOverlay" aria-hidden="true" hidden>
    <div class="loading-box">
        <div class="loading-title"><?= t('calc_in_progress') ?></div>
        <div class="progress-track"><div class="progress-bar" id="progressBar"></div></div>
        <div class="loading-sub" id="loadingStep"><?= t('initializing') ?></div>
    </div>
</div>

<div class="page-wrap">
    <header class="site-header">
        <div class="logo-icon"><img src="img/logo.svg" alt="FuelFinder"></div>
        <div class="logo-text">Fuel<span>Finder</span></div>
        <div class="header-badge"><?= t('header_badge') ?></div>
        <div class="lang-switcher" title="<?= t('lang_label') ?>">
            <a href="?lang=it" class="lang-opt<?= currentLang()==='it'?' active':'' ?>">IT</a>
            <a href="?lang=de" class="lang-opt<?= currentLang()==='de'?' active':'' ?>">DE</a>
        </div>
    </header>

    <nav class="page-nav">
        <a href="index.php" class="nav-tab"><?= t('nav_nearby') ?></a>
        <a href="route.php" class="nav-tab active"><?= t('nav_route') ?></a>
    </nav>

    <div class="layout route-layout">
        <aside class="panel-left">

            <form method="POST" id="routeForm" class="form-card">
                <div class="section-label"><?= t('nav_route') ?></div>

                <!-- FROM -->
                <div class="field">
                    <label><?= t('route_from') ?></label>
                    <div class="route-addr-wrap">
                        <input type="text" id="fromInput"
                               placeholder="<?= htmlspecialchars(t('route_from_ph')) ?>"
                               autocomplete="off">
                        <input type="hidden" name="from_lat"   id="fromLatInput"   value="<?= htmlspecialchars($valFromLat) ?>">
                        <input type="hidden" name="from_lon"   id="fromLonInput"   value="<?= htmlspecialchars($valFromLon) ?>">
                        <input type="hidden" name="from_label" id="fromLabelInput" value="<?= htmlspecialchars($valFromLabel) ?>">
                        <div class="addr-suggestions-wrap" id="fromSuggestions"></div>
                    </div>
                    <button type="button" id="gpsFromBtn" class="btn-ghost btn-cyan"
                            style="display:none;margin-top:6px;width:100%;justify-content:center">
                        <?= t('route_gps_use') ?>
                    </button>
                </div>

                <!-- TO -->
                <div class="field">
                    <label><?= t('route_to') ?></label>
                    <div class="route-addr-wrap">
                        <input type="text" id="toInput"
                               placeholder="<?= htmlspecialchars(t('route_to_ph')) ?>"
                               autocomplete="off">
                        <input type="hidden" name="to_lat"   id="toLatInput"   value="<?= htmlspecialchars($valToLat) ?>">
                        <input type="hidden" name="to_lon"   id="toLonInput"   value="<?= htmlspecialchars($valToLon) ?>">
                        <input type="hidden" name="to_label" id="toLabelInput" value="<?= htmlspecialchars($valToLabel) ?>">
                        <div class="addr-suggestions-wrap" id="toSuggestions"></div>
                    </div>
                    <button type="button" id="gpsToBtn" class="btn-ghost btn-cyan"
                            style="display:none;margin-top:6px;width:100%;justify-content:center">
                        <?= t('route_gps_use_to') ?>
                    </button>
                </div>

                <!-- FUEL + CONSUMPTION -->
                <div class="row-2">
                    <div class="field">
                        <label><?= t('fuel') ?></label>
                        <select name="tipo">
                            <option value="benzina" <?= $valTipo==='benzina'?'selected':'' ?>><?= t('fuel_benzina') ?></option>
                            <option value="gasolio" <?= $valTipo==='gasolio'?'selected':'' ?>><?= t('fuel_gasolio') ?></option>
                            <option value="gpl"     <?= $valTipo==='gpl'    ?'selected':'' ?>><?= t('fuel_gpl') ?></option>
                            <option value="metano"  <?= $valTipo==='metano' ?'selected':'' ?>><?= t('fuel_metano') ?></option>
                        </select>
                    </div>
                    <div class="field">
                        <label><?= t('car_consumption') ?></label>
                        <input type="number" name="consumo" step="0.1" inputmode="decimal"
                               value="<?= htmlspecialchars($valConsumo) ?>"
                               placeholder="<?= t('consumption_ph') ?>">
                    </div>
                </div>

                <!-- CORRIDOR + MAX KM -->
                <div class="row-2">
                    <div class="field">
                        <label><?= t('route_corridor') ?></label>
                        <select name="corridor">
                            <option value="1"  <?= $valCorridor==='1' ?'selected':'' ?>>1 km</option>
                            <option value="2"  <?= $valCorridor==='2' ?'selected':'' ?>>2 km</option>
                            <option value="5"  <?= $valCorridor==='5'||$valCorridor===''?'selected':'' ?>>5 km</option>
                            <option value="10" <?= $valCorridor==='10'?'selected':'' ?>>10 km</option>
                        </select>
                    </div>
                    <div class="field">
                        <label><?= t('route_maxkm_label') ?></label>
                        <select name="max_km">
                            <option value="0"   <?= $valMaxKm==='0'||$valMaxKm===''?'selected':'' ?>><?= t('route_maxkm_all') ?></option>
                            <option value="50"  <?= $valMaxKm==='50' ?'selected':'' ?>>50 km</option>
                            <option value="100" <?= $valMaxKm==='100'?'selected':'' ?>>100 km</option>
                            <option value="200" <?= $valMaxKm==='200'?'selected':'' ?>>200 km</option>
                            <option value="300" <?= $valMaxKm==='300'?'selected':'' ?>>300 km</option>
                        </select>
                    </div>
                </div>

                <!-- AVOID OPTIONS -->
                <div class="field">
                    <label><?= t('route_avoid_label') ?></label>
                    <div class="avoid-options">
                        <label class="avoid-opt">
                            <input type="checkbox" name="avoid_motorway" value="1"
                                   <?= $valAvoidMotorway ? 'checked' : '' ?>>
                            <?= t('route_avoid_motorway') ?>
                        </label>
                        <label class="avoid-opt">
                            <input type="checkbox" name="avoid_toll" value="1"
                                   <?= $valAvoidToll ? 'checked' : '' ?>>
                            <?= t('route_avoid_toll') ?>
                        </label>
                    </div>
                </div>

                <button type="submit" name="calc_route" class="calc-btn" id="calcBtn"
                        <?= (!$valFromLat || !$valToLat) ? 'disabled' : '' ?>>
                    <?= t('route_calc') ?>
                </button>

                <div id="route-status" style="font-size:0.75rem;font-family:'JetBrains Mono',monospace;color:var(--muted);text-align:center;min-height:18px"></div>
            </form>

            <?php if ($routeError): ?>
            <div class="route-error-box">
                <span>&#9888;</span> <?= htmlspecialchars($routeError) ?>
            </div>
            <?php endif; ?>

            <?php if ($routeResult): ?>
            <div class="route-meta">
                <span class="route-meta-item">
                    <span class="route-meta-icon">&#128205;</span>
                    <strong><?= $routeResult['distance_km'] ?> km</strong>
                    <span class="route-meta-label"><?= t('route_total_dist') ?></span>
                </span>
                <span class="route-meta-sep">·</span>
                <span class="route-meta-item">
                    <span class="route-meta-icon">&#128336;</span>
                    <strong><?= $routeResult['duration_min'] ?> <?= t('route_min') ?></strong>
                    <span class="route-meta-label"><?= t('route_duration') ?></span>
                </span>
            </div>
            <?php endif; ?>

            <?php if ($hasResults): ?>
            <div id="route-results-anchor">
                <div class="results-header">
                    <span class="results-title"><?= t('route_found') ?></span>
                    <span class="results-count"><?= count($routeStations) ?> <?= t('results_count') ?></span>
                </div>
                <div class="results-grid">
                    <?php foreach ($routeStations as $idx => $s): ?>
                    <div class="res-card route-res-card <?= $idx===0?'highlight':'' ?>"
                         data-idx="<?= $idx ?>"
                         onclick="if(window.focusStationOnMap)focusStationOnMap(<?= $idx ?>)"
                         style="cursor:pointer">
                        <div class="card-top">
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= $s['lat'] ?>,<?= $s['lon'] ?>"
                               target="_blank" rel="noopener" class="map-link"
                               onclick="event.stopPropagation()">
                                <div class="card-name">
                                    &#128205;
                                    <span class="route-card-idx"><?= $idx+1 ?>.</span>
                                    <?= htmlspecialchars($s['name']) ?>
                                    <?php if ($idx===0): ?>
                                        <span class="best-badge"><?= t('route_best') ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-addr" style="color:var(--muted)"><?= htmlspecialchars($s['brand']) ?></div>
                            </a>
                            <div class="card-price">
                                <div class="price-value">EUR <?= number_format($s['price'], 3, '.', '') ?></div>
                                <div class="price-unit"><?= t('per_liter') ?></div>
                            </div>
                        </div>

                        <div class="route-card-badges">
                            <span class="km-badge">
                                <?= $s['km_along'] ?> <?= t('route_km_along') ?>
                            </span>
                            <?php if ($s['detour_km'] > 0.1): ?>
                            <span class="detour-badge">
                                +<?= $s['detour_km'] ?> <?= t('route_detour') ?>
                            </span>
                            <?php else: ?>
                            <span class="detour-badge detour-badge-on">
                                <?= t('route_on_route') ?>
                            </span>
                            <?php endif; ?>
                        </div>

                        <div class="route-card-breakeven">
                            <?php if ($s['break_even'] === 0): ?>
                                <span class="breakeven-badge breakeven-green">&#10003; <?= t('route_on_route') ?></span>
                            <?php elseif ($s['break_even'] === -1): ?>
                                <span class="breakeven-badge breakeven-gold">&#9733; <?= t('route_cheapest_offroute') ?></span>
                            <?php elseif ($s['break_even'] === null): ?>
                                <span class="breakeven-badge breakeven-red"><?= t('route_never') ?></span>
                            <?php else: ?>
                                <span class="breakeven-badge breakeven-gold">
                                    <?= t('route_breakeven') ?> <?= $s['break_even'] ?> <?= t('route_breakeven_l') ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php elseif (!$routeError): ?>
            <div class="empty-state">
                <div class="empty-icon">&#128205;</div>
                <p><?= t('route_empty') ?></p>
                <div class="hint"><?= t('empty_hint') ?></div>
            </div>
            <?php endif; ?>

        </aside>

        <main class="panel-right route-panel-right">
            <div id="routeMap" class="route-map-panel"></div>
        </main>
    </div>
</div>

<script>
const ROUTE_COORDS   = <?= $routeResult ? json_encode($routeResult['coords']) : 'null' ?>;
const ROUTE_STATIONS = <?= $hasResults ? json_encode(array_map(fn($s,$i)=>['idx'=>$i,'lat'=>$s['lat'],'lon'=>$s['lon'],'nome'=>$s['name'].' ('.$s['brand'].')','price'=>$s['price'],'km_along'=>$s['km_along'],'detour_km'=>$s['detour_km'],'break_even'=>$s['break_even']],$routeStations,array_keys($routeStations))) : '[]' ?>;
const ROUTE_FROM     = {lat:<?=(float)($valFromLat?:0)?>,lon:<?=(float)($valFromLon?:0)?>,label:<?=json_encode($valFromLabel, JSON_HEX_TAG)?>};
const ROUTE_TO       = {lat:<?=(float)($valToLat?:0)?>,lon:<?=(float)($valToLon?:0)?>,label:<?=json_encode($valToLabel, JSON_HEX_TAG)?>};
const HAS_RESULTS    = <?= $hasResults ? 'true' : 'false' ?>;
const FF_LANG        = <?= json_encode(currentLang()) ?>;
window.FF_T          = <?= json_encode($LANG[currentLang()], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="js/route.js"></script>
</body>
</html>
