// Escape per i popup Leaflet (bindPopup renderizza HTML): nomi stazione
// arrivano dalle API esterne, le label partenza/arrivo dall'utente.
function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

document.addEventListener('DOMContentLoaded', function() {

    // --- LOADING OVERLAY ---
    var progressTimer = null;

    function showLoadingOverlay() {
        var ov = document.getElementById('loadingOverlay');
        if (!ov) return;
        ov.removeAttribute('hidden');
        ov.classList.add('is-visible');

        var bar  = document.getElementById('progressBar');
        var step = document.getElementById('loadingStep');
        if (!bar || !step) return;

        var T = window.FF_T || {};
        var steps = [
            { at: 0,    pct: 8,  text: T.route_loading_1 || 'Calcolo percorso…' },
            { at: 800,  pct: 30, text: T.route_loading_2 || 'Cerco distributori sul tragitto…' },
            { at: 3000, pct: 65, text: T.route_loading_3 || 'Filtro e calcolo convenienza…' },
            { at: 7000, pct: 90, text: T.route_loading_4 || 'Quasi pronto…' }
        ];

        bar.style.width = '0%';
        step.textContent = T.initializing || '…';

        var start = Date.now();
        if (progressTimer) clearInterval(progressTimer);
        progressTimer = setInterval(function() {
            var elapsed = Date.now() - start;
            var active = steps[0];
            for (var i = 0; i < steps.length; i++) {
                if (elapsed >= steps[i].at) active = steps[i];
            }
            var next = null;
            for (var j = 0; j < steps.length; j++) {
                if (steps[j].at > elapsed) { next = steps[j]; break; }
            }
            var targetPct = active.pct;
            if (next) {
                var span = next.at - active.at;
                var done = Math.min(1, (elapsed - active.at) / span);
                targetPct = active.pct + (next.pct - active.pct) * done;
            } else {
                var extra = Math.min(8, (elapsed - active.at) / 1000);
                targetPct = Math.min(98, active.pct + extra);
            }
            bar.style.width = targetPct.toFixed(1) + '%';
            if (step.textContent !== active.text) step.textContent = active.text;
        }, 120);
    }

    // Hide overlay on bfcache restore
    window.addEventListener('pageshow', function(e) {
        var ov = document.getElementById('loadingOverlay');
        if (!ov) return;
        ov.classList.remove('is-visible');
        ov.setAttribute('hidden', '');
        if (progressTimer) { clearInterval(progressTimer); progressTimer = null; }
    });

    document.getElementById('routeForm').addEventListener('submit', function() {
        showLoadingOverlay();
    });

    // --- MAP INIT ---
    // Guard against Leaflet load failure — without try/catch a missing L
    // aborts the rest of DOMContentLoaded, breaking GPS buttons & autocomplete.
    if (typeof L === 'undefined') {
        console.error('Leaflet (L) not loaded — map unavailable, continuing without map');
        var map = null;
    }
    if (typeof L !== 'undefined') try {
    var map = L.map('routeMap', { zoomControl: true }).setView([44.5, 11.3], 6);
    L.tileLayer('/tiles.php?z={z}&x={x}&y={y}', {
        attribution: '&copy; <a href="https://www.geoapify.com/">Geoapify</a> | &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 20
    }).addTo(map);

    // Icons defined once, reused for route markers and draggable preview pins
    var startIcon = L.divIcon({
        html: '<div class="map-marker map-marker-start">A</div>',
        className: '', iconSize: [28, 28], iconAnchor: [14, 14]
    });
    var endIcon = L.divIcon({
        html: '<div class="map-marker map-marker-end">B</div>',
        className: '', iconSize: [28, 28], iconAnchor: [14, 14]
    });

    // Draggable preview pins — shown when user selects an address before calculating route.
    // Dragging updates the hidden lat/lon inputs so the route uses the corrected position.
    var fromPin = null;
    var toPin   = null;

    function placeDraggablePin(existingPin, lat, lon, icon, latInputId, lonInputId, labelInputId) {
        var pos = L.latLng(lat, lon);
        if (existingPin) {
            existingPin.setLatLng(pos);
            map.flyTo(pos, 16, { animate: true, duration: 0.6 });
            return existingPin;
        }
        var T = window.FF_T || {};
        var dragHint = T.route_drag_hint || 'Trascina per correggere la posizione';
        var pin = L.marker(pos, { icon: icon, draggable: true }).addTo(map);
        pin.bindTooltip(dragHint, { permanent: true, direction: 'top', offset: [0, -16] });
        pin.on('dragstart', function() { pin.closeTooltip(); });
        pin.on('dragend', function() {
            var ll = pin.getLatLng();
            document.getElementById(latInputId).value   = ll.lat.toFixed(6);
            document.getElementById(lonInputId).value   = ll.lng.toFixed(6);
            var lbl = document.getElementById(labelInputId);
            lbl.value = lbl.value.replace(' ✎', '') + ' ✎';
            pin.bindTooltip(dragHint, { permanent: true, direction: 'top', offset: [0, -16] });
            pin.openTooltip();
        });
        map.flyTo(pos, 16, { animate: true, duration: 0.6 });
        return pin;
    }

    // Draw route if we have data
    if (ROUTE_COORDS && ROUTE_COORDS.length > 1) {
        // OSRM GeoJSON returns [lon, lat]; Leaflet wants [lat, lon]
        var latlngs = ROUTE_COORDS.map(function(c) { return [c[1], c[0]]; });
        var routeLine = L.polyline(latlngs, {
            color: '#3b82f6',
            weight: 5,
            opacity: 0.85
        }).addTo(map);

        var startCoord = [ROUTE_COORDS[0][1], ROUTE_COORDS[0][0]];
        var endCoord   = [ROUTE_COORDS[ROUTE_COORDS.length - 1][1], ROUTE_COORDS[ROUTE_COORDS.length - 1][0]];

        L.marker(startCoord, { icon: startIcon }).addTo(map)
            .bindPopup('<strong>' + (escapeHtml(ROUTE_FROM.label) || 'Partenza') + '</strong>');
        L.marker(endCoord, { icon: endIcon }).addTo(map)
            .bindPopup('<strong>' + (escapeHtml(ROUTE_TO.label) || 'Arrivo') + '</strong>');

        // Station markers
        var stationMarkers = [];
        ROUTE_STATIONS.forEach(function(s) {
            var num   = s.idx + 1;
            var color = s.idx === 0 ? '#047857' : (s.idx < 3 ? '#0369a1' : '#475569');
            var icon  = L.divIcon({
                html: '<div class="map-marker-station" style="background:' + color + '">' +
                      '<span><b>' + num + '.</b> ' + s.price.toFixed(3) + '</span></div>',
                className: '',
                iconSize: [68, 24],
                iconAnchor: [34, 12]
            });
            var detourText = s.detour_km <= 0.1
                ? ((window.FF_T && window.FF_T.route_on_route) || 'Sul percorso')
                : '+' + s.detour_km + ' km ' + ((window.FF_T && window.FF_T.route_detour) || 'fuori rotta');
            var popupHtml = '<strong>' + num + '. ' + escapeHtml(s.nome) + '</strong><br>' +
                'EUR ' + s.price.toFixed(3) + '/L<br>' +
                s.km_along + ' km · ' + detourText;
            var m = L.marker([s.lat, s.lon], { icon: icon }).addTo(map).bindPopup(popupHtml);
            m.on('click', function() { scrollToCard(s.idx); });
            stationMarkers.push(m);
        });

        var allBounds = routeLine.getBounds();
        stationMarkers.forEach(function(m) { allBounds.extend(m.getLatLng()); });
        map.fitBounds(allBounds.pad(0.08));

        window.focusStationOnMap = function(idx) {
            if (stationMarkers[idx]) {
                map.setView(stationMarkers[idx].getLatLng(), 14, { animate: true });
                stationMarkers[idx].openPopup();
            }
        };
    }
    } catch (e) {
        console.error('Map init failed:', e);
        map = null;
    }

    function scrollToCard(idx) {
        var card = document.querySelector('.route-res-card[data-idx="' + idx + '"]');
        if (card) {
            card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            card.classList.add('card-flash');
            setTimeout(function() { card.classList.remove('card-flash'); }, 1000);
        }
    }

    // --- GPS FOR FROM/TO FIELDS ---
    var gpsFromBtn = document.getElementById('gpsFromBtn');
    var gpsToBtn   = document.getElementById('gpsToBtn');
    var calcBtn    = document.getElementById('calcBtn');
    var fromLatGps = 0;
    var fromLonGps = 0;

    function updateCalcBtn() {
        var fLat = document.getElementById('fromLatInput').value;
        var tLat = document.getElementById('toLatInput').value;
        calcBtn.disabled = !(fLat && fLat !== '0' && tLat && tLat !== '0');
    }

    function setFrom(lat, lon, label) {
        document.getElementById('fromLatInput').value   = lat;
        document.getElementById('fromLonInput').value   = lon;
        document.getElementById('fromLabelInput').value = label;
        document.getElementById('fromInput').value      = label;
        updateCalcBtn();
        if (!HAS_RESULTS && map && typeof placeDraggablePin === 'function') {
            try {
                fromPin = placeDraggablePin(fromPin, lat, lon, startIcon,
                    'fromLatInput', 'fromLonInput', 'fromLabelInput');
            } catch (e) { console.error('placeDraggablePin from:', e); }
        }
    }

    function setTo(lat, lon, label) {
        document.getElementById('toLatInput').value   = lat;
        document.getElementById('toLonInput').value   = lon;
        document.getElementById('toLabelInput').value = label;
        document.getElementById('toInput').value      = label;
        updateCalcBtn();
        if (!HAS_RESULTS && map && typeof placeDraggablePin === 'function') {
            try {
                toPin = placeDraggablePin(toPin, lat, lon, endIcon,
                    'toLatInput', 'toLonInput', 'toLabelInput');
            } catch (e) { console.error('placeDraggablePin to:', e); }
        }
    }

    // Restore form state after POST
    if (ROUTE_FROM.lat && ROUTE_FROM.lat !== 0) {
        document.getElementById('fromLatInput').value = ROUTE_FROM.lat;
        document.getElementById('fromLonInput').value = ROUTE_FROM.lon;
        document.getElementById('fromInput').value    = ROUTE_FROM.label || '';
    }
    if (ROUTE_TO.lat && ROUTE_TO.lat !== 0) {
        document.getElementById('toLatInput').value = ROUTE_TO.lat;
        document.getElementById('toLonInput').value = ROUTE_TO.lon;
        document.getElementById('toInput').value    = ROUTE_TO.label || '';
    }
    updateCalcBtn();

    // GPS
    if ('geolocation' in navigator) {
        gpsFromBtn.style.display = 'flex';
        gpsFromBtn.disabled = true;
        gpsFromBtn.textContent = (window.FF_T && window.FF_T.gps_wait) || '📍 Attesa GPS…';
        if (gpsToBtn) {
            gpsToBtn.style.display = 'flex';
            gpsToBtn.disabled = true;
            gpsToBtn.textContent = (window.FF_T && window.FF_T.gps_wait) || '📍 Attesa GPS…';
        }

        navigator.geolocation.getCurrentPosition(function(p) {
            fromLatGps = p.coords.latitude;
            fromLonGps = p.coords.longitude;
            gpsFromBtn.textContent = (window.FF_T && window.FF_T.route_gps_use) || '📍 Usa GPS come partenza';
            gpsFromBtn.disabled = false;
            if (gpsToBtn) {
                gpsToBtn.textContent = (window.FF_T && window.FF_T.route_gps_use_to) || '📍 Usa GPS come arrivo';
                gpsToBtn.disabled = false;
            }
        }, function() {
            gpsFromBtn.style.display = 'none';
            if (gpsToBtn) gpsToBtn.style.display = 'none';
        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });

        gpsFromBtn.addEventListener('click', function() {
            if (!fromLatGps) return;
            var label = (window.FF_T && window.FF_T.route_gps_ready) || 'GPS pronto come partenza';
            setFrom(fromLatGps, fromLonGps, label);
            if (gpsToBtn) { gpsToBtn.disabled = true; gpsToBtn.style.opacity = '0.4'; }
        });

        if (gpsToBtn) {
            gpsToBtn.addEventListener('click', function() {
                if (!fromLatGps) return;
                var label = (window.FF_T && window.FF_T.route_gps_ready_to) || 'GPS pronto come arrivo';
                setTo(fromLatGps, fromLonGps, label);
                gpsFromBtn.disabled = true;
                gpsFromBtn.style.opacity = '0.4';
            });
        }
    }

    // --- NOMINATIM AUTOCOMPLETE ---
    function setupAutocomplete(inputId, suggestId, onSelect) {
        var inp   = document.getElementById(inputId);
        var sugg  = document.getElementById(suggestId);
        var timer = null;

        function hideSugg() {
            sugg.style.display = 'none';
            sugg.innerHTML = '';
        }

        inp.addEventListener('input', function() {
            var q = this.value.trim();
            clearTimeout(timer);
            hideSugg();
            if (q.length < 3) return;
            timer = setTimeout(function() {
                fetch('/geocode.php?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .catch(function() { return []; })
                    .then(function(allItems) {
                    if (!Array.isArray(allItems)) allItems = [];
                    allItems.sort(function(a, b) {
                        return ((b.address && b.address.house_number) ? 1 : 0)
                             - ((a.address && a.address.house_number) ? 1 : 0);
                    });

                    sugg.innerHTML = '';
                    if (!allItems.length) { hideSugg(); return; }
                    var seen = {};
                    var data = allItems.filter(function(item) {
                        var a  = item.address || {};
                        var st = (a.road || a.pedestrian || a.path || '').toLowerCase();
                        var cn = (a.house_number || '').toLowerCase();
                        var ci = (a.city || a.town || a.village || a.municipality || '').toLowerCase();
                        var key = (st && cn)
                            ? (st + '|' + cn + '|' + ci)
                            : (parseFloat(item.lat).toFixed(4) + ',' + parseFloat(item.lon).toFixed(4));
                        if (seen[key]) return false;
                        seen[key] = true;
                        return true;
                    });
                    if (!data.length) { hideSugg(); return; }
                    data.slice(0, 5).forEach(function(item) {
                        var a  = item.address || {};
                        var st = a.road || a.pedestrian || a.path || '';
                        var cn = a.house_number || '';
                        var ci = a.city || a.town || a.village || a.municipality || '';
                        if (!cn && st) {
                            var firstPart = item.display_name.split(',')[0].trim();
                            if (/^\d+[a-zA-Z]?$/.test(firstPart)) cn = firstPart;
                        }
                        if (!cn && st) {
                            var qNum = q.match(/\b(\d+[a-zA-Z]?)\b/);
                            if (qNum) cn = qNum[1];
                        }
                        var main = (st && cn)
                            ? st + ' ' + cn + (ci ? ', ' + ci : '')
                            : item.display_name.split(', ').slice(0, 3).join(', ');
                        var div = document.createElement('div');
                        div.className = 'addr-suggestion';
                        var mainEl = document.createElement('span');
                        mainEl.textContent = main;
                        div.appendChild(mainEl);
                        if (ci && !main.includes(ci)) {
                            var sm = document.createElement('small');
                            sm.textContent = ci;
                            div.appendChild(sm);
                        }
                        div.addEventListener('mousedown', function(e) {
                            e.preventDefault();
                            hideSugg();
                            onSelect(parseFloat(item.lat), parseFloat(item.lon), main);
                        });
                        sugg.appendChild(div);
                    });
                    sugg.style.display = 'block';
                });
            }, 280);
        });

        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') hideSugg();
            if (e.key === 'Enter') e.preventDefault();
        });
        inp.addEventListener('blur', function() {
            setTimeout(hideSugg, 150);
        });
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#' + suggestId) && e.target !== inp) hideSugg();
        });
    }

    setupAutocomplete('fromInput', 'fromSuggestions', function(lat, lon, label) {
        setFrom(lat, lon, label);
    });
    setupAutocomplete('toInput', 'toSuggestions', function(lat, lon, label) {
        setTo(lat, lon, label);
    });

    // Scroll to results if present
    if (HAS_RESULTS) {
        var anchor = document.getElementById('route-results-anchor');
        if (anchor) setTimeout(function() { anchor.scrollIntoView({ behavior: 'smooth' }); }, 300);
    }
});
