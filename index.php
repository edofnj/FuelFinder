<?php
require 'includes/config.php';
require 'includes/api.php';
require 'includes/data.php';
?>
<!DOCTYPE html>
<html lang="it">
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



<div class="page-wrap">
    <header class="site-header">
        <div class="logo-icon">&#9981;</div>
        <div class="logo-text">Fuel<span>Finder</span></div>
        <div class="header-badge">MIMIT · Dati ufficiali</div>
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
                <button type="submit" class="sos-btn" id="sosBtn" disabled>&#128680; SOS &mdash; Il pi&ugrave; vicino!</button>
            </form>

            <!-- GARAGE -->
            <div class="garage-card">
                <div class="garage-header">
                    <div class="garage-header-left" onclick="toggleGarage()" style="flex:1;cursor:pointer">
                        <span>&#128663;</span>
                        <span class="garage-title">Il mio garage</span>
                        <span class="garage-count" id="garageCount">0</span>
                    </div>
                    <button type="button" class="garage-add-btn" id="garageAddHeaderBtn" onclick="openAddForm()" title="Aggiungi veicolo">&#43;</button>
                    <span class="garage-chevron" id="garageChevron" onclick="toggleGarage()" style="cursor:pointer">&#9660;</span>
                </div>
                <div class="garage-body" id="garageBody">
                    <div class="vehicle-list" id="vehicleList">
                        <div class="garage-empty" id="garageEmpty">// Nessun veicolo salvato</div>
                    </div>
                    <div class="add-vehicle-form" id="addVehicleForm">
                        <input type="hidden" id="vEditId">
                        <div class="field">
                            <label id="vFormLabel">Nome veicolo</label>
                            <input type="text" id="vNome" placeholder="Es: Panda, Golf...">
                        </div>
                        <div class="row-2">
                            <div class="field">
                                <label>Carburante</label>
                                <select id="vTipo">
                                    <option value="benzina">Benzina</option>
                                    <option value="gasolio">Gasolio</option>
                                    <option value="gpl">GPL</option>
                                    <option value="metano">Metano</option>
                                </select>
                            </div>
                            <div class="field">
                                <label>L/100km</label>
                                <input type="number" id="vConsumo" step="0.1" placeholder="Es: 7.5" inputmode="decimal">
                            </div>
                        </div>
                        <div class="garage-actions">
                            <button type="button" class="btn-ghost btn-muted" onclick="closeAddForm()">Annulla</button>
                            <button type="button" class="btn-ghost btn-green" id="vSaveBtn" onclick="saveVehicle()">&#10003; Salva</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MAIN FORM -->
            <form method="POST" id="mainForm">
                <div class="form-card">
                    <div class="section-label">Impostazioni ricerca</div>

                    <div class="row-2">
                        <div class="field">
                            <label>Carburante</label>
                            <select name="tipo">
                                <option value="benzina" <?= $valTipo=='benzina'?'selected':'' ?>>Benzina</option>
                                <option value="gasolio" <?= $valTipo=='gasolio'?'selected':'' ?>>Gasolio</option>
                                <option value="gpl"     <?= $valTipo=='gpl'    ?'selected':'' ?>>GPL</option>
                                <option value="metano"  <?= $valTipo=='metano' ?'selected':'' ?>>Metano</option>
                            </select>
                        </div>
                        <div class="field">
                            <label>Raggio</label>
                            <select name="raggio">
                                <option value="5"  <?= $valRaggio=='5' ?'selected':'' ?>>5 km</option>
                                <option value="10" <?= $valRaggio=='10'?'selected':'' ?>>10 km</option>
                                <option value="20" <?= $valRaggio=='20'?'selected':'' ?>>20 km</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <label>Consumo auto (L/100 km)</label>
                        <input type="number" step="0.1" name="consumo"
                               value="<?= htmlspecialchars($valConsumo) ?>"
                               placeholder="Es: 7.5" inputmode="decimal">
                    </div>

                    <div class="row-2">
                        <div class="field">
                            <label id="labelQta"><?= $valModo=='litri'?'Litri':'Budget (EUR)' ?></label>
                            <input type="number" step="0.1" name="quantita"
                                   value="<?= htmlspecialchars($valQuantita) ?>"
                                   inputmode="decimal">
                        </div>
                        <div class="field">
                            <label>Unit&agrave;</label>
                            <select name="modo" id="modoSelect"
                                    onchange="document.getElementById('labelQta').innerText=this.value==='litri'?'Litri':'Budget (EUR)'">
                                <option value="litri" <?= $valModo=='litri'?'selected':'' ?>>Litri</option>
                                <option value="euro"  <?= $valModo=='euro' ?'selected':'' ?>>Euro</option>
                            </select>
                        </div>
                    </div>

                    <!-- BRAND FILTER -->
                    <div class="brand-filter-wrap">
                        <div class="brand-filter-header" onclick="toggleBrandFilter()">
                            <div class="brand-filter-left">
                                <span class="section-label">Filtra marche</span>
                                <span class="brand-filter-count" id="brandFilterCount">tutte</span>
                            </div>
                            <span class="brand-filter-chevron" id="brandChevron">&#9660;</span>
                        </div>
                        <div class="brand-filter-body" id="brandFilterBody">
                            <div class="brand-filter-actions">
                                <button type="button" class="btn-brand-all" onclick="selectAllBrands()">&#10003; Tutte</button>
                                <button type="button" class="btn-brand-none" onclick="deselectAllBrands()">&#10005; Nessuna</button>
                            </div>
                            <div class="brand-checkbox-grid" id="brandCheckboxGrid">
                                <span style="color:var(--muted);font-size:0.75rem;font-family:'JetBrains Mono',monospace">Caricamento...</span>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="marche_json" id="marcheJson" value="">
                    <input type="hidden" name="lat" class="lat-hidden">
                    <input type="hidden" name="lon" class="lon-hidden">

                    <button type="submit" name="calc" class="calc-btn" id="calcBtn" disabled>&#8627; Calcola Convenienza</button>
                    <div id="gps-status" style="color:#e3b341;">&#128205; Attesa segnale GPS&hellip;</div>

                    <input type="hidden" name="addr_label" id="addrLabelInput">

                    <!-- Indirizzo manuale -->
                    <div id="addr-wrap">
                        <button type="button" class="addr-toggle" id="addrToggleBtn">
                            &#9998; Inserisci indirizzo manualmente
                        </button>
                        <div id="addr-box" style="display:none;margin-top:8px;position:relative;">
                            <input type="text" id="addrInput" placeholder="Es: Via Roma 1, Milano" autocomplete="off">
                            <div id="addrHint" style="font-size:0.72rem;color:var(--muted);margin-top:4px;font-family:'JetBrains Mono',monospace">// via + numero civico obbligatori</div>
                            <div id="addrSuggestions"></div>
                        </div>
                        <button type="button" class="btn-ghost btn-cyan" id="gpsSwitchBtn"
                                style="display:none;width:100%;margin-top:8px;justify-content:center">
                            &#128205; Usa GPS (disponibile)
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
                    <span class="results-title">Distributori trovati</span>
                    <span class="results-count"><?= count($results) ?> risultati</span>
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
                                            <?php if($isSOS): ?><span class="sos-badge">VICINO</span>
                                            <?php else: ?><span class="best-badge">BEST</span><?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-addr"><?= htmlspecialchars($r['addr']) ?></div>
                                </a>
                                <div class="card-price">
                                    <div class="price-value">EUR <?= number_format($r['prezzo'],3) ?></div>
                                    <div class="price-unit">al litro</div>
                                </div>
                            </div>
                            <?php if (!$r['is_sos']): ?>
                            <div class="card-breakdown">
                                <?php if ($r['modo'] === 'euro'): ?>
                                <div class="breakdown-row">
                                    <span class="breakdown-label">&#9981; Budget carburante</span>
                                    <span class="breakdown-val">€ <?= number_format($r['spesa_carb'], 2) ?></span>
                                </div>
                                <div class="breakdown-row">
                                    <span class="breakdown-label">&#128663; Viaggio (<?= $r['litri_v'] ?> L)</span>
                                    <span class="breakdown-val">€ <?= number_format($r['costo_v'], 2) ?></span>
                                </div>
                                <div class="breakdown-row breakdown-total">
                                    <span class="breakdown-label">Litri netti ottenuti</span>
                                    <span class="breakdown-val"><?= number_format($r['valore'], 2) ?> L</span>
                                </div>
                                <?php else: ?>
                                <div class="breakdown-row">
                                    <span class="breakdown-label">&#9981; Carburante</span>
                                    <span class="breakdown-val">€ <?= number_format($r['spesa_carb'], 2) ?></span>
                                </div>
                                <div class="breakdown-row">
                                    <span class="breakdown-label">&#128663; Viaggio (<?= $r['litri_v'] ?> L)</span>
                                    <span class="breakdown-val">€ <?= number_format($r['costo_v'], 2) ?></span>
                                </div>
                                <div class="breakdown-row breakdown-total">
                                    <span class="breakdown-label">Totale</span>
                                    <span class="breakdown-val">€ <?= number_format($r['totale'], 2) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                            <div class="card-footer">
                                <span class="dist-badge"><?= $r['distanza'] ?> km</span>
                                <?php if (!empty($r['data'])): ?>
                                <span class="date-badge" title="Ultimo aggiornamento prezzo">&#128197; <?= htmlspecialchars($r['data']) ?></span>
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
                    <p>Imposta le opzioni e premi <strong>Calcola Convenienza</strong> per trovare il distributore pi&ugrave; economico vicino a te.</p>
                    <div class="hint">// dati in tempo reale dal MIMIT</div>
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
                <h2>Benvenuto in FuelFinder</h2>
                <p>Scopri come trovare il carburante pi&ugrave; conveniente</p>
            </div>
        </div>
        <div class="tutorial-steps" id="tutorialSteps"></div>
        <div class="tutorial-footer">
            <div class="tutorial-dots" id="tutorialDots"></div>
            <div style="display:flex;gap:10px;align-items:center">
                <button class="tutorial-btn-skip" onclick="tutorialClose()">Salta</button>
                <button class="tutorial-btn-back" id="tutorialBtnBack" onclick="tutorialBack()" style="display:none">&#8592; Indietro</button>
                <button class="tutorial-btn-next" id="tutorialBtnNext" onclick="tutorialNext()">Avanti &#8594;</button>
            </div>
        </div>
    </div>
</div>

<button class="tutorial-btn-help" onclick="tutorialOpen()" title="Tutorial">?</button>

<script>
const FF_NEEDS_UPDATE = false;
const FF_HAS_RESULTS  = <?= ($isSOS || isset($_POST['calc'])) ? 'true' : 'false' ?>;
const FF_SAVED_LAT    = <?= isset($_POST['lat']) ? (float)$_POST['lat'] : 0 ?>;
const FF_SAVED_LON    = <?= isset($_POST['lon']) ? (float)$_POST['lon'] : 0 ?>;
const FF_ADDR_LABEL   = <?= json_encode($_POST['addr_label'] ?? '') ?>;
</script>
<script src="js/tutorial.js"></script>
<script src="js/app.js"></script>
</body>
</html>
