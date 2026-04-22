<?php
require 'includes/config.php';
require 'includes/i18n.php';
require 'includes/api.php';
require 'includes/data.php';
?>
<!DOCTYPE html>
<html lang="<?= currentLang() ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>FuelFinder</title>
<link rel="icon" type="image/png" href="https://cdn-icons-png.flaticon.com/512/483/483497.png">
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0d1117">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<link rel="apple-touch-icon" href="https://cdn-icons-png.flaticon.com/512/483/483497.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
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
        <div class="logo-icon">&#9981;</div>
        <div class="logo-text">Fuel<span>Finder</span></div>
        <div class="header-badge"><?= t('header_badge') ?></div>
        <div class="lang-switcher" title="<?= t('lang_label') ?>">
            <a href="?lang=it" class="lang-opt<?= currentLang()==='it'?' active':'' ?>">IT</a>
            <a href="?lang=de" class="lang-opt<?= currentLang()==='de'?' active':'' ?>">DE</a>
        </div>
    </header>

    <div class="layout">
        <aside class="panel-left">

            <!-- SOS -->
            <form method="POST" id="sosForm">
                <input type="hidden" name="lat" class="lat-hidden">
                <input type="hidden" name="lon" class="lon-hidden">
                <input type="hidden" name="sos_mode" value="1">
                <input type="hidden" name="consumo" id="sosConsumo">
                <input type="hidden" name="quantita" id="sosQuantita">
                <input type="hidden" name="modo" id="sosModo">
                <input type="hidden" name="marche_json" id="sosMarcheJson">
                <input type="hidden" name="addr_label" id="sosAddrLabel">
                <button type="submit" class="sos-btn" id="sosBtn" disabled><?= t('sos_btn') ?></button>
            </form>

            <!-- GARAGE -->
            <div class="garage-card">
                <div class="garage-header">
                    <div class="garage-header-left" onclick="toggleGarage()" style="flex:1;cursor:pointer">
                        <span>&#128663;</span>
                        <span class="garage-title"><?= t('garage_title') ?></span>
                        <span class="garage-count" id="garageCount">0</span>
                    </div>
                    <button type="button" class="garage-add-btn" id="garageAddHeaderBtn" onclick="openAddForm()" title="<?= t('garage_add_title') ?>">&#43;</button>
                    <span class="garage-chevron" id="garageChevron" onclick="toggleGarage()" style="cursor:pointer">&#9660;</span>
                </div>
                <div class="garage-body" id="garageBody">
                    <div class="vehicle-list" id="vehicleList">
                        <div class="garage-empty" id="garageEmpty"><?= t('garage_empty') ?></div>
                    </div>
                    <div class="add-vehicle-form" id="addVehicleForm">
                        <input type="hidden" id="vEditId">
                        <div class="field">
                            <label id="vFormLabel"><?= t('veh_name') ?></label>
                            <input type="text" id="vNome" placeholder="<?= t('veh_name_ph') ?>">
                        </div>
                        <div class="row-2">
                            <div class="field">
                                <label><?= t('fuel') ?></label>
                                <select id="vTipo">
                                    <option value="benzina"><?= t('fuel_benzina') ?></option>
                                    <option value="gasolio"><?= t('fuel_gasolio') ?></option>
                                    <option value="gpl"><?= t('fuel_gpl') ?></option>
                                    <option value="metano"><?= t('fuel_metano') ?></option>
                                </select>
                            </div>
                            <div class="field">
                                <label><?= t('consumption') ?></label>
                                <input type="number" id="vConsumo" step="0.1" placeholder="<?= t('consumption_ph') ?>" inputmode="decimal">
                            </div>
                        </div>
                        <div class="garage-actions">
                            <button type="button" class="btn-ghost btn-muted" onclick="closeAddForm()"><?= t('cancel') ?></button>
                            <button type="button" class="btn-ghost btn-green" id="vSaveBtn" onclick="saveVehicle()"><?= t('save') ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAIN FORM -->
            <form method="POST" id="mainForm">
                <div class="form-card">
                    <div class="section-label"><?= t('search_settings') ?></div>

                    <div class="row-2">
                        <div class="field">
                            <label><?= t('fuel') ?></label>
                            <select name="tipo" id="tipoSelect">
                                <option value="benzina" data-countries="IT,DE" <?= $valTipo=='benzina'?'selected':'' ?>><?= t('fuel_benzina') ?></option>
                                <option value="gasolio" data-countries="IT,DE" <?= $valTipo=='gasolio'?'selected':'' ?>><?= t('fuel_gasolio') ?></option>
                                <option value="gpl"     data-countries="IT"    <?= $valTipo=='gpl'    ?'selected':'' ?>><?= t('fuel_gpl') ?></option>
                                <option value="metano"  data-countries="IT"    <?= $valTipo=='metano' ?'selected':'' ?>><?= t('fuel_metano') ?></option>
                            </select>
                        </div>
                        <div class="field">
                            <label><?= t('radius') ?></label>
                            <select name="raggio">
                                <option value="5"  <?= $valRaggio=='5' ?'selected':'' ?>>5 km</option>
                                <option value="10" <?= $valRaggio=='10'?'selected':'' ?>>10 km</option>
                                <option value="20" <?= $valRaggio=='20'?'selected':'' ?>>20 km</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label><?= t('car_consumption') ?></label>
                        <input type="number" step="0.1" name="consumo"
                               value="<?= htmlspecialchars($valConsumo) ?>"
                               placeholder="<?= t('consumption_ph') ?>" inputmode="decimal">
                    </div>

                    <div class="row-2">
                        <div class="field">
                            <label id="labelQta"><?= $valModo=='litri'?t('liters'):t('budget_eur') ?></label>
                            <input type="number" step="0.1" name="quantita"
                                   value="<?= htmlspecialchars($valQuantita) ?>"
                                   inputmode="decimal">
                        </div>
                        <div class="field">
                            <label><?= t('unit') ?></label>
                            <select name="modo" id="modoSelect"
                                    onchange="document.getElementById('labelQta').innerText=this.value==='litri'?window.FF_T.liters:window.FF_T.budget_eur">
                                <option value="litri" <?= $valModo=='litri'?'selected':'' ?>><?= t('liters') ?></option>
                                <option value="euro"  <?= $valModo=='euro' ?'selected':'' ?>><?= t('euro') ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- BRAND FILTER -->
                    <div class="brand-filter-wrap">
                        <div class="brand-filter-header" onclick="toggleBrandFilter()">
                            <div class="brand-filter-left">
                                <span class="section-label"><?= t('filter_brands') ?></span>
                                <span class="brand-filter-count" id="brandFilterCount"><?= t('all_f') ?></span>
                            </div>
                            <span class="brand-filter-chevron" id="brandChevron">&#9660;</span>
                        </div>
                        <div class="brand-filter-body" id="brandFilterBody">
                            <div class="brand-filter-actions">
                                <button type="button" class="btn-brand-all" onclick="selectAllBrands()"><?= t('all_btn') ?></button>
                                <button type="button" class="btn-brand-none" onclick="deselectAllBrands()"><?= t('none_btn') ?></button>
                            </div>
                            <div class="brand-checkbox-grid" id="brandCheckboxGrid">
                                <span style="color:var(--muted);font-size:0.75rem;font-family:'JetBrains Mono',monospace"><?= t('loading') ?></span>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="marche_json" id="marcheJson" value="">
                    <input type="hidden" name="lat" class="lat-hidden">
                    <input type="hidden" name="lon" class="lon-hidden">

                    <button type="submit" name="calc" class="calc-btn" id="calcBtn" disabled><?= t('calc_btn') ?></button>
                    <div id="gps-status" style="color:#e3b341;"><?= t('gps_wait') ?></div>

                    <input type="hidden" name="addr_label" id="addrLabelInput">

                    <!-- Indirizzo manuale -->
                    <div id="addr-wrap">
                        <button type="button" class="addr-toggle" id="addrToggleBtn">
                            <?= t('manual_addr') ?>
                        </button>
                        <div id="addr-box" style="display:none;margin-top:8px;position:relative;">
                            <input type="text" id="addrInput" placeholder="<?= t('addr_ph') ?>" autocomplete="off">
                            <div id="addrHint" style="font-size:0.72rem;color:var(--muted);margin-top:4px;font-family:'JetBrains Mono',monospace"><?= t('addr_hint') ?></div>
                            <div id="addrSuggestions"></div>
                        </div>
                        <button type="button" class="btn-ghost btn-cyan" id="gpsSwitchBtn"
                                style="display:none;width:100%;margin-top:8px;justify-content:center">
                            <?= t('gps_use') ?>
                        </button>
                    </div>

                </div>
            </form>

        </aside>

        <main class="panel-right">
            <?php if (!empty($apiError)): ?>
                <div class="empty-state" style="border-color:var(--orange)">
                    <div class="empty-icon">&#9888;</div>
                    <p><?= htmlspecialchars($apiError) ?></p>
                </div>
            <?php elseif ($results): ?>
                <div class="results-header">
                    <span class="results-title"><?= t('found_stations') ?></span>
                    <span class="results-count"><?= count($results) ?> <?= t('results_count') ?></span>
                </div>
                <div class="results-grid" id="results-anchor">
                    <?php foreach ($results as $i => $r): ?>
                        <div class="res-card <?= $i===0?($isSOS?'sos-highlight':'highlight'):'' ?>">
                            <div class="card-top">
                                <a href="https://www.google.com/maps/search/?api=1&query=<?= $r['lat'] ?>,<?= $r['lon'] ?>"
                                   target="_blank" class="map-link">
                                    <div class="card-name">
                                        &#128205;
                                        <?= htmlspecialchars($r['nome']) ?>
                                        <?php if($i===0): ?>
                                            <?php if($isSOS): ?><span class="sos-badge"><?= t('sos_badge') ?></span>
                                            <?php else: ?><span class="best-badge"><?= t('best_badge') ?></span><?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-addr"><?= htmlspecialchars($r['addr']) ?></div>
                                </a>
                                <div class="card-price">
                                    <div class="price-value">EUR <?= number_format($r['prezzo'],3) ?></div>
                                    <div class="price-unit"><?= t('per_liter') ?></div>
                                </div>
                            </div>
                            <?php if (!$r['is_sos']): ?>
                            <div class="card-breakdown">
                                <?php if ($r['modo'] === 'euro'): ?>
                                <div class="breakdown-row">
                                    <span class="breakdown-label"><?= t('fuel_budget') ?></span>
                                    <span class="breakdown-val">€ <?= number_format($r['spesa_carb'], 2) ?></span>
                                </div>
                                <div class="breakdown-row">
                                    <span class="breakdown-label"><?= t('trip_cost') ?> (<?= $r['litri_v'] ?> L)</span>
                                    <span class="breakdown-val">€ <?= number_format($r['costo_v'], 2) ?></span>
                                </div>
                                <div class="breakdown-row breakdown-total">
                                    <span class="breakdown-label"><?= t('net_liters') ?></span>
                                    <span class="breakdown-val"><?= number_format($r['valore'], 2) ?> L</span>
                                </div>
                                <?php else: ?>
                                <div class="breakdown-row">
                                    <span class="breakdown-label"><?= t('fuel_cost') ?></span>
                                    <span class="breakdown-val">€ <?= number_format($r['spesa_carb'], 2) ?></span>
                                </div>
                                <div class="breakdown-row">
                                    <span class="breakdown-label"><?= t('trip_cost') ?> (<?= $r['litri_v'] ?> L)</span>
                                    <span class="breakdown-val">€ <?= number_format($r['costo_v'], 2) ?></span>
                                </div>
                                <div class="breakdown-row breakdown-total">
                                    <span class="breakdown-label"><?= t('total') ?></span>
                                    <span class="breakdown-val">€ <?= number_format($r['totale'], 2) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="card-footer">
                                <span class="dist-badge" title="<?= !empty($r['road_ok']) ? t('dist_road') : t('dist_air') ?>"><?= !empty($r['road_ok']) ? '' : '~' ?><?= $r['distanza'] ?> km</span>
                                <?php if (!empty($r['data'])): ?>
                                <span class="date-badge" title="<?= t('last_price_update') ?>">&#128197; <?= htmlspecialchars($r['data']) ?></span>
                                <?php endif; ?>
                                <?php if ($r['is_sos']): ?>
                                <span class="card-label">€ <?= number_format($r['prezzo'], 3) ?>/L</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">&#9981;</div>
                    <p><?= t('empty_msg_html') ?></p>
                    <div class="hint"><?= t('empty_hint') ?></div>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- TUTORIAL -->
<div class="tutorial-overlay" id="tutorialOverlay" style="display:none">
    <div class="tutorial-box">
        <div class="tutorial-header">
            <div class="tutorial-header-icon">&#9981;</div>
            <div class="tutorial-header-text">
                <h2><?= t('tutorial_welcome') ?></h2>
                <p><?= t('tutorial_subtitle') ?></p>
            </div>
        </div>
        <div class="tutorial-steps" id="tutorialSteps"></div>
        <div class="tutorial-footer">
            <div class="tutorial-dots" id="tutorialDots"></div>
            <div style="display:flex;gap:10px;align-items:center">
                <button class="tutorial-btn-skip" onclick="tutorialClose()"><?= t('tutorial_skip') ?></button>
                <button class="tutorial-btn-back" id="tutorialBtnBack" onclick="tutorialBack()" style="display:none"><?= t('tutorial_back') ?></button>
                <button class="tutorial-btn-next" id="tutorialBtnNext" onclick="tutorialNext()"><?= t('tutorial_next') ?></button>
            </div>
        </div>
    </div>
</div>

<button class="tutorial-btn-help" onclick="tutorialOpen()" title="<?= t('tutorial_help') ?>">?</button>

<script>
const FF_NEEDS_UPDATE = false;
const FF_HAS_RESULTS  = <?= ($isSOS || isset($_POST['calc'])) ? 'true' : 'false' ?>;
const FF_SAVED_LAT    = <?= isset($_POST['lat']) ? (float)$_POST['lat'] : 0 ?>;
const FF_SAVED_LON    = <?= isset($_POST['lon']) ? (float)$_POST['lon'] : 0 ?>;
const FF_ADDR_LABEL   = <?= json_encode($_POST['addr_label'] ?? '') ?>;
const FF_LANG         = <?= json_encode(currentLang()) ?>;
window.FF_T = <?= json_encode($LANG[currentLang()], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="js/tutorial.js"></script>
<script src="js/app.js"></script>
</body>
</html>
