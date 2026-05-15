document.addEventListener('DOMContentLoaded', function() {

    if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');

    // FORM VALIDATION
    document.getElementById('mainForm').addEventListener('submit', function(e) {
        var lat  = this.querySelector('.lat-hidden').value;
        var cons = this.querySelector('input[name="consumo"]').value;
        var qta  = this.querySelector('input[name="quantita"]').value;
        var errors = [];
        if (!lat || lat === '0') errors.push((window.FF_T && window.FF_T.err_gps) || 'GPS');
        if (!cons || parseFloat(cons) <= 0) errors.push((window.FF_T && window.FF_T.err_consumption) || 'Consumo');
        if (!qta  || parseFloat(qta)  <= 0) errors.push((window.FF_T && window.FF_T.err_liters) || 'Litri');

        if (errors.length > 0) {
            e.preventDefault();
            var st2 = document.getElementById('gps-status');
            st2.innerText = errors[0];
            st2.style.color = 'var(--red)';
            if (!cons || parseFloat(cons) <= 0) {
                var ci = document.querySelector('input[name="consumo"]');
                ci.style.borderColor = 'var(--red)';
                ci.focus();
            } else if (!qta || parseFloat(qta) <= 0) {
                var qi2 = document.querySelector('input[name="quantita"]');
                qi2.style.borderColor = 'var(--red)';
                qi2.focus();
            }
            ['consumo','quantita'].forEach(function(n){
                var el = document.querySelector('input[name="'+n+'"]');
                if(el) el.addEventListener('input', function(){ this.style.borderColor=''; }, {once:true});
            });
            return;
        }
        showLoadingOverlay(false);
    });

    // SOS — copia valori da mainForm prima del submit
    document.getElementById('sosForm').addEventListener('submit', function() {
        document.getElementById('sosConsumo').value    = document.querySelector('#mainForm input[name="consumo"]').value;
        document.getElementById('sosQuantita').value   = document.querySelector('#mainForm input[name="quantita"]').value;
        document.getElementById('sosModo').value       = document.querySelector('#mainForm select[name="modo"]').value;
        document.getElementById('sosAddrLabel').value  = document.getElementById('addrLabelInput').value;
        showLoadingOverlay(true);
    });

    var progressTimer = null;

    function showLoadingOverlay(isSOS) {
        var ov = document.getElementById('loadingOverlay');
        if (!ov) return;
        ov.removeAttribute('hidden');
        ov.classList.add('is-visible');

        var bar = document.getElementById('progressBar');
        var step = document.getElementById('loadingStep');
        if (!bar || !step) return;

        // Step plausibili: il server fa MIMIT -> (Tankerkoenig se DE) -> OSRM parallelo -> calcoli
        var T = window.FF_T || {};
        var steps = isSOS ? [
            { at: 0,    pct: 10, text: T.step_sos_1 },
            { at: 400,  pct: 40, text: T.step_sos_2 },
            { at: 1200, pct: 75, text: T.step_sos_3 },
            { at: 2400, pct: 92, text: T.step_sos_4 }
        ] : [
            { at: 0,    pct: 8,  text: T.step_1 },
            { at: 300,  pct: 22, text: T.step_2 },
            { at: 1400, pct: 45, text: T.step_3 },
            { at: 3000, pct: 68, text: T.step_4 },
            { at: 5000, pct: 85, text: T.step_5 },
            { at: 7500, pct: 93, text: T.step_6 }
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
            // Interpolazione morbida fino al prossimo step
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
                // Dopo ultimo step, avanzamento lento asintotico al 98%
                var extra = Math.min(8, (elapsed - active.at) / 1000);
                targetPct = Math.min(98, active.pct + extra);
            }
            bar.style.width = targetPct.toFixed(1) + '%';
            if (step.textContent !== active.text) step.textContent = active.text;
        }, 120);
    }
    // Nascondi overlay se utente torna con back/forward (bfcache)
    window.addEventListener('pageshow', function(e) {
        var ov = document.getElementById('loadingOverlay');
        if (!ov) return;
        ov.classList.remove('is-visible');
        ov.setAttribute('hidden', '');
        if (progressTimer) { clearInterval(progressTimer); progressTimer = null; }
    });

    // GPS + INDIRIZZO MANUALE
    var st             = document.getElementById('gps-status');
    var addrBox        = document.getElementById('addr-box');
    var addrInput      = document.getElementById('addrInput');
    var addrSugg       = document.getElementById('addrSuggestions');
    var addrBtn        = document.getElementById('addrToggleBtn');
    var addrLabelInput = document.getElementById('addrLabelInput');
    var gpsSwitchBtn   = document.getElementById('gpsSwitchBtn');
    var addrTimer      = null;
    var manualActive   = false;   // true quando l'utente usa un indirizzo manuale
    var gpsLat = 0, gpsLon = 0;  // coords GPS quando disponibile

    function detectCountry(lat, lon) {
        if (lat >= 35.4 && lat <= 47.2 && lon >= 6.5 && lon <= 18.6) return 'IT';
        if (lat >= 47.2 && lat <= 55.1 && lon >= 5.8 && lon <= 15.1) return 'DE';
        return null;
    }

    function updateFuelOptions(lat, lon) {
        var cc = detectCountry(parseFloat(lat), parseFloat(lon));
        if (!cc) return;
        var sel = document.getElementById('tipoSelect');
        if (!sel) return;
        var current = sel.value;
        var firstAvailable = null;
        Array.prototype.forEach.call(sel.options, function(opt){
            var allowed = (opt.getAttribute('data-countries') || '').split(',');
            var ok = allowed.indexOf(cc) !== -1;
            opt.hidden = !ok;
            opt.disabled = !ok;
            if (ok && firstAvailable === null) firstAvailable = opt.value;
        });
        // Se opzione attuale non più disponibile, fallback alla prima valida
        var curOpt = sel.querySelector('option[value="' + current + '"]');
        if (curOpt && (curOpt.hidden || curOpt.disabled) && firstAvailable) {
            sel.value = firstAvailable;
        }
    }

    function gpsSetCoords(lat, lon, label, isManual) {
        document.querySelectorAll('.lat-hidden').forEach(function(el){ el.value = lat; });
        document.querySelectorAll('.lon-hidden').forEach(function(el){ el.value = lon; });
        updateFuelOptions(lat, lon);
        document.getElementById('calcBtn').disabled = false;
        document.getElementById('sosBtn').disabled  = false;
        st.innerText = '✅ ' + (label || 'Posizione impostata');
        st.style.color = isManual ? 'var(--cyan)' : 'var(--accent)';
        addrBtn.style.color = isManual ? 'var(--cyan)' : 'var(--accent)';
        if (isManual) {
            manualActive = true;
            addrLabelInput.value = label || '';
            // Se il GPS è già disponibile, mostra il tasto per tornare al GPS
            if (gpsLat !== 0) gpsSwitchBtn.style.display = 'block';
        } else {
            manualActive = false;
            addrLabelInput.value = '';
            gpsSwitchBtn.style.display = 'none';
        }
    }

    function gpsOpenManual() {
        addrBox.style.display = 'block';
        addrBtn.style.color = 'var(--cyan)';
        addrInput.focus();
    }

    gpsSwitchBtn.addEventListener('click', function() {
        var T = window.FF_T || {};
        gpsSetCoords(gpsLat, gpsLon, T.gps_ready || 'GPS', false);
        addrBox.style.display = 'none';
        addrInput.value = '';
    });

    addrBtn.addEventListener('click', function() {
        var isOpen = addrBox.style.display !== 'none';
        addrBox.style.display = isOpen ? 'none' : 'block';
        if (!isOpen) addrInput.focus();
    });

    // Ripristina indirizzo manuale dopo submit del form
    if (FF_ADDR_LABEL && FF_SAVED_LAT !== 0) {
        manualActive = true;
        addrInput.value = FF_ADDR_LABEL;
        addrBox.style.display = 'block';
        addrLabelInput.value = FF_ADDR_LABEL;
        gpsSetCoords(FF_SAVED_LAT, FF_SAVED_LON, FF_ADDR_LABEL, true);
    }

    var T2 = window.FF_T || {};
    if ('geolocation' in navigator) {
        if (!FF_ADDR_LABEL) {
            st.innerText = T2.gps_searching || '📍 GPS…';
            st.style.color = '#e3b341';
        }
        navigator.geolocation.getCurrentPosition(
            function(p) {
                gpsLat = p.coords.latitude;
                gpsLon = p.coords.longitude;
                if (manualActive) {
                    gpsSwitchBtn.style.display = 'block';
                } else {
                    gpsSetCoords(gpsLat, gpsLon, T2.gps_ready || 'GPS', false);
                }
            },
            function() {
                if (!manualActive) {
                    st.innerText = T2.gps_not_found || 'GPS ❌';
                    st.style.color = 'var(--red)';
                    gpsOpenManual();
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else {
        if (!manualActive) {
            st.innerText = T2.gps_unavailable || 'GPS ❌';
            st.style.color = 'var(--red)';
            gpsOpenManual();
        }
    }

    // Autocomplete Nominatim
    function addrHideSugg() {
        addrSugg.style.display = 'none';
        addrSugg.innerHTML = '';
    }

    // Controlla che la stringa contenga almeno un numero (= numero civico)
    function addrHasCivico(q) { return /\d/.test(q); }

    addrInput.addEventListener('input', function() {
        var q = this.value.trim();
        clearTimeout(addrTimer);
        addrHideSugg();

        var T = window.FF_T || {};
        var hint = document.getElementById('addrHint');
        if (q.length > 2 && !addrHasCivico(q)) {
            hint.textContent = T.addr_need_num;
            hint.style.color = 'var(--accent)';
            return;
        } else {
            hint.textContent = T.addr_hint;
            hint.style.color = 'var(--muted)';
        }

        if (q.length < 4 || !addrHasCivico(q)) return;

        addrTimer = setTimeout(function() {
            var base = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=it,de&addressdetails=1';
            var hdrs = { 'Accept-Language': 'it', 'User-Agent': 'FuelFinder/1.0' };
            var fetchList = [
                fetch(base + '&layer=address&q=' + encodeURIComponent(q), { headers: hdrs })
                    .then(function(r) { return r.json(); }).catch(function() { return []; })
            ];
            var numM = q.match(/^([^0-9,]+?)\s+(\d+[a-zA-Z]?)\s*(?:,\s*(.+))?$/);
            if (numM) {
                var sStreet = numM[1].trim();
                var sNum    = numM[2];
                var sCity   = (numM[3] || '').trim();
                var structUrl = base + '&housenumber=' + encodeURIComponent(sNum)
                              + '&street=' + encodeURIComponent(sStreet)
                              + (sCity ? '&city=' + encodeURIComponent(sCity) : '');
                fetchList.push(
                    fetch(structUrl, { headers: hdrs })
                        .then(function(r) { return r.json(); }).catch(function() { return []; })
                );
            }
            Promise.all(fetchList).then(function(results) {
                var allItems = [];
                results.forEach(function(r) { if (Array.isArray(r)) allItems = allItems.concat(r); });
                allItems.sort(function(a, b) {
                    return ((b.address && b.address.house_number) ? 1 : 0)
                         - ((a.address && a.address.house_number) ? 1 : 0);
                });
                addrSugg.innerHTML = '';
                if (!allItems.length) { addrHideSugg(); return; }
                var seen = {};
                var data = allItems.filter(function(item) {
                    var a = item.address || {};
                    var street = (a.road || a.pedestrian || a.path || '').toLowerCase();
                    var civ    = (a.house_number || '').toLowerCase();
                    var city   = (a.city || a.town || a.village || a.municipality || '').toLowerCase();
                    var key = (street && civ) ? (street + '|' + civ + '|' + city)
                                              : (parseFloat(item.lat).toFixed(4) + ',' + parseFloat(item.lon).toFixed(4));
                    if (seen[key]) return false;
                    seen[key] = true;
                    return true;
                });
                if (!data.length) { addrHideSugg(); return; }
                data.slice(0, 5).forEach(function(item) {
                    var div = document.createElement('div');
                    div.className = 'addr-suggestion';
                    var a = item.address || {};
                    var street = a.road || a.pedestrian || a.path || '';
                    var civico = a.house_number || '';
                    var city   = a.city || a.town || a.village || a.municipality || '';
                    if (!civico && street) {
                        var firstPart = item.display_name.split(',')[0].trim();
                        if (/^\d+[a-zA-Z]?$/.test(firstPart)) civico = firstPart;
                    }
                    if (!civico && street) {
                        var qNum = q.match(/\b(\d+[a-zA-Z]?)\b/);
                        if (qNum) civico = qNum[1];
                    }
                    var main   = (street && civico) ? street + ' ' + civico + (city ? ', ' + city : '')
                                                    : item.display_name.split(', ').slice(0, 3).join(', ');
                    var detail = city && !(main.includes(city)) ? city : '';
                    var mainEl = document.createElement('span');
                    mainEl.textContent = main;
                    div.appendChild(mainEl);
                    if (detail) {
                        var sm = document.createElement('small');
                        sm.textContent = detail;
                        div.appendChild(sm);
                    }
                    div.addEventListener('mousedown', function(e) {
                        e.preventDefault();
                        addrInput.value = main;
                        addrHideSugg();
                        gpsSetCoords(parseFloat(item.lat), parseFloat(item.lon), main, true);
                        addrBox.style.display = 'none';
                    });
                    addrSugg.appendChild(div);
                });
                addrSugg.style.display = 'block';
            });
        }, 250);
    });

    addrInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') addrHideSugg();
        if (e.key === 'Enter') e.preventDefault();
    });

    addrInput.addEventListener('blur', function() {
        setTimeout(addrHideSugg, 150);
    });

    document.addEventListener('click', function(e) {
        if (!addrBox.contains(e.target) && e.target !== addrBtn) addrHideSugg();
    });

    if (FF_HAS_RESULTS) {
        var anchor = document.getElementById('results-anchor');
        if (anchor) anchor.scrollIntoView({behavior:'smooth'});
    }

    // GARAGE
    var GKEY = 'fuelfinder_garage', AKEY = 'fuelfinder_active';
    function lsGet(k)   { try{return JSON.parse(localStorage.getItem(k));}catch(e){return null;} }
    function lsSet(k,v) { try{localStorage.setItem(k,JSON.stringify(v));}catch(e){} }
    function lsDel(k)   { try{localStorage.removeItem(k);}catch(e){} }
    function loadG()    { return lsGet(GKEY)||[]; }
    function saveG(l)   { lsSet(GKEY,l); }

    var FL={benzina:'Benzina',gasolio:'Gasolio',gpl:'GPL',metano:'Metano'};
    function esc(s){return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}

    function renderGarage() {
        var list=loadG(), activeId=String(lsGet(AKEY)||'');
        var container=document.getElementById('vehicleList');
        var empty=document.getElementById('garageEmpty');
        var count=document.getElementById('garageCount');
        if(!container) return;
        count.textContent=list.length;
        empty.style.display=list.length===0?'block':'none';
        container.querySelectorAll('.vehicle-item').forEach(function(el){el.remove();});
        list.forEach(function(v){
            var item=document.createElement('div');
            item.className='vehicle-item'+(v.id===activeId?' active':'');
            item.innerHTML=
                '<span class="vehicle-icon">'+(FL[v.tipo]||v.tipo).substring(0,1)+'</span>'+
                '<div class="vehicle-info">'+
                    '<div class="vehicle-name">'+esc(v.nome)+'</div>'+
                    '<div class="vehicle-meta">'+(FL[v.tipo]||v.tipo)+' &middot; '+v.consumo+' L/100km</div>'+
                '</div>'+
                '<button class="vehicle-del vehicle-edit" data-vid="'+esc(v.id)+'" title="Modifica">&#9998;</button>'+
                '<button class="vehicle-del" data-vid="'+esc(v.id)+'" title="Elimina">&#10005;</button>';
            item.querySelector('.vehicle-edit').addEventListener('click',function(e){
                e.stopPropagation();
                openEditForm(this.dataset.vid);
            });
            item.querySelector('.vehicle-del:not(.vehicle-edit)').addEventListener('click',function(e){
                e.stopPropagation();
                var vid=this.dataset.vid;
                saveG(loadG().filter(function(x){return x.id!==vid;}));
                if(String(lsGet(AKEY))===vid) lsDel(AKEY);
                renderGarage();
            });
            item.addEventListener('click',function(){selectVehicle(v.id);});
            container.appendChild(item);
        });
    }

    function selectVehicle(id) {
        var v=loadG().find(function(x){return x.id===String(id);});
        if(!v) return;
        lsSet(AKEY,v.id);
        var ts=document.querySelector('#mainForm select[name="tipo"]');
        var ci=document.querySelector('#mainForm input[name="consumo"]');
        if(ts) ts.value=v.tipo;
        if(ci) ci.value=v.consumo;
        renderGarage();
    }

    window.saveVehicle=function(){
        var ni=document.getElementById('vNome');
        var ci2=document.getElementById('vConsumo');
        var nome=ni.value.trim();
        var tipo=document.getElementById('vTipo').value;
        var consumo=parseFloat(ci2.value);
        var editId=document.getElementById('vEditId').value;
        ni.style.borderColor=''; ci2.style.borderColor='';
        if(!nome){ni.style.borderColor='var(--red)';ni.focus();return;}
        if(!consumo||consumo<=0){ci2.style.borderColor='var(--red)';ci2.focus();return;}
        if(editId) {
            // Modifica veicolo esistente
            var l=loadG().map(function(x){
                return x.id===editId ? {id:x.id,nome:nome,tipo:tipo,consumo:consumo} : x;
            });
            saveG(l);
            window.closeAddForm();
            renderGarage();
            selectVehicle(editId);
        } else {
            // Nuovo veicolo
            var nv={id:String(Date.now()),nome:nome,tipo:tipo,consumo:consumo};
            var l=loadG(); l.push(nv); saveG(l);
            window.closeAddForm();
            renderGarage();
            selectVehicle(nv.id);
        }
    };

    function openEditForm(id) {
        var v=loadG().find(function(x){return x.id===String(id);});
        if(!v) return;
        document.getElementById('vEditId').value=v.id;
        document.getElementById('vNome').value=v.nome;
        document.getElementById('vTipo').value=v.tipo;
        document.getElementById('vConsumo').value=v.consumo;
        document.getElementById('vFormLabel').textContent='Modifica veicolo';
        document.getElementById('vSaveBtn').textContent='✓ Aggiorna';
        document.getElementById('addVehicleForm').classList.add('open');
        document.getElementById('garageAddHeaderBtn').style.display='none';
        document.getElementById('garageBody').classList.add('open');
        document.getElementById('garageChevron').classList.add('open');
        setTimeout(function(){document.getElementById('vNome').focus();},50);
    }

    window.openAddForm=function(){
        document.getElementById('vEditId').value='';
        document.getElementById('vFormLabel').textContent='Nome veicolo';
        document.getElementById('vSaveBtn').textContent='✓ Salva';
        document.getElementById('addVehicleForm').classList.add('open');
        document.getElementById('garageAddHeaderBtn').style.display='none';
        document.getElementById('garageBody').classList.add('open');
        document.getElementById('garageChevron').classList.add('open');
        setTimeout(function(){document.getElementById('vNome').focus();},50);
    };
    window.closeAddForm=function(){
        document.getElementById('addVehicleForm').classList.remove('open');
        document.getElementById('garageAddHeaderBtn').style.display='';
        document.getElementById('vEditId').value='';
        document.getElementById('vFormLabel').textContent='Nome veicolo';
        document.getElementById('vSaveBtn').textContent='✓ Salva';
        document.getElementById('vNome').value='';
        document.getElementById('vNome').style.borderColor='';
        document.getElementById('vConsumo').value='';
        document.getElementById('vConsumo').style.borderColor='';
    };
    window.toggleGarage=function(){
        document.getElementById('garageBody').classList.toggle('open');
        document.getElementById('garageChevron').classList.toggle('open');
    };

    renderGarage();
    var initList=loadG();
    if(initList.length>0){
        document.getElementById('garageBody').classList.add('open');
        document.getElementById('garageChevron').classList.add('open');
        var av=lsGet(AKEY);
        if(av){
            var vv=initList.find(function(x){return x.id===String(av);});
            if(vv){
                var ts2=document.querySelector('#mainForm select[name="tipo"]');
                var ci3=document.querySelector('#mainForm input[name="consumo"]');
                if(ts2) ts2.value=vv.tipo;
                if(ci3) ci3.value=vv.consumo;
            }
        }
    }


}); // end DOMContentLoaded

