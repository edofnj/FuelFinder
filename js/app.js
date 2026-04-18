document.addEventListener('DOMContentLoaded', function() {

    if ('serviceWorker' in navigator) navigator.serviceWorker.register('sw.js');

    // FORM VALIDATION
    document.getElementById('mainForm').addEventListener('submit', function(e) {
        var lat  = this.querySelector('.lat-hidden').value;
        var cons = this.querySelector('input[name="consumo"]').value;
        var qta  = this.querySelector('input[name="quantita"]').value;
        var errors = [];
        if (!lat || lat === '0') errors.push('GPS non disponibile — abilita la posizione');
        if (!cons || parseFloat(cons) <= 0) errors.push('Inserisci il consumo dell\u2019auto (L/100km)');
        if (!qta  || parseFloat(qta)  <= 0) errors.push('Inserisci i litri o il budget');

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
        }
    });

    // SOS — copia valori da mainForm prima del submit
    document.getElementById('sosForm').addEventListener('submit', function() {
        document.getElementById('sosConsumo').value    = document.querySelector('#mainForm input[name="consumo"]').value;
        document.getElementById('sosQuantita').value   = document.querySelector('#mainForm input[name="quantita"]').value;
        document.getElementById('sosModo').value       = document.querySelector('#mainForm select[name="modo"]').value;
        document.getElementById('sosMarcheJson').value = document.getElementById('marcheJson').value;
        document.getElementById('sosAddrLabel').value  = document.getElementById('addrLabelInput').value;
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

    function gpsSetCoords(lat, lon, label, isManual) {
        document.querySelectorAll('.lat-hidden').forEach(function(el){ el.value = lat; });
        document.querySelectorAll('.lon-hidden').forEach(function(el){ el.value = lon; });
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
        gpsSetCoords(gpsLat, gpsLon, 'GPS pronto', false);
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

    if ('geolocation' in navigator) {
        if (!FF_ADDR_LABEL) {
            st.innerText = '📍 Ricerca GPS in corso…';
            st.style.color = '#e3b341';
        }
        navigator.geolocation.getCurrentPosition(
            function(p) {
                gpsLat = p.coords.latitude;
                gpsLon = p.coords.longitude;
                if (manualActive) {
                    // GPS arrivato ma l'utente sta usando l'indirizzo: mostra solo il tasto switch
                    gpsSwitchBtn.style.display = 'block';
                } else {
                    gpsSetCoords(gpsLat, gpsLon, 'GPS pronto', false);
                }
            },
            function() {
                if (!manualActive) {
                    st.innerText = '❌ GPS non trovato — inserisci un indirizzo';
                    st.style.color = 'var(--red)';
                    gpsOpenManual();
                }
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    } else {
        if (!manualActive) {
            st.innerText = '❌ GPS non disponibile — inserisci un indirizzo';
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

        var hint = document.getElementById('addrHint');
        if (q.length > 2 && !addrHasCivico(q)) {
            hint.textContent = '// aggiungi il numero civico per una posizione precisa';
            hint.style.color = 'var(--accent)';
            return;
        } else {
            hint.textContent = '// via + numero civico obbligatori';
            hint.style.color = 'var(--muted)';
        }

        if (q.length < 4 || !addrHasCivico(q)) return;

        addrTimer = setTimeout(function() {
            // Con civico: layer=address prioritizza risultati a livello di numero civico
            var url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&countrycodes=it&addressdetails=1&layer=address&q=' + encodeURIComponent(q);
            fetch(url, { headers: { 'Accept-Language': 'it', 'User-Agent': 'FuelFinder/1.0' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                addrSugg.innerHTML = '';
                if (!data || !data.length) { addrHideSugg(); return; }
                data.forEach(function(item) {
                    var div = document.createElement('div');
                    div.className = 'addr-suggestion';
                    // Costruisce label leggibile con civico in evidenza
                    var a = item.address || {};
                    var street = a.road || a.pedestrian || a.path || '';
                    var civico = a.house_number || '';
                    var city   = a.city || a.town || a.village || a.municipality || '';
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
            })
            .catch(function() { addrHideSugg(); });
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

    // BRAND FILTER
    window._bf = {
        brands: [],
        sel: {},
        loaded: false
    };
    var BKEY = 'fuelfinder_brands_sel';

    function bfSave() {
        var desel = window._bf.brands.filter(function(b){ return !window._bf.sel[b]; });
        try {
            if (desel.length === 0) localStorage.removeItem(BKEY);
            else localStorage.setItem(BKEY, JSON.stringify(desel));
        } catch(e) {}
    }

    function bfRestore() {
        try {
            var desel = JSON.parse(localStorage.getItem(BKEY));
            if (Array.isArray(desel)) {
                desel.forEach(function(b) { window._bf.sel[b] = false; });
            }
        } catch(e) {}
    }

    function bfUpdateCount() {
        var el  = document.getElementById('brandFilterCount');
        var hid = document.getElementById('marcheJson');
        if (!el) return;
        var all = window._bf.brands;
        var sel = all.filter(function(b){ return window._bf.sel[b]; });
        el.textContent = (sel.length === all.length) ? 'tutte' : sel.length + '/' + all.length;
        if (hid) hid.value = (sel.length === all.length) ? '' : JSON.stringify(sel);
        bfSave();
    }

    function bfRender() {
        var grid = document.getElementById('brandCheckboxGrid');
        if (!grid) return;
        var all = window._bf.brands;

        grid.innerHTML = '';

        if (all.length === 0) {
            grid.innerHTML = '<span style="color:var(--muted);font-size:0.75rem;grid-column:1/-1">Nessuna marca trovata</span>';
            bfUpdateCount();
            return;
        }

        all.forEach(function(b) {
            var lbl = document.createElement('label');
            lbl.className = 'brand-cb-label';
            lbl.title = b;

            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.checked = !!window._bf.sel[b];
            cb.addEventListener('change', function() {
                window._bf.sel[b] = this.checked;
                bfUpdateCount();
            });
            lbl.appendChild(cb);
            var txt = document.createElement('span');
            txt.textContent = b;
            lbl.appendChild(txt);
            grid.appendChild(lbl);
        });

        bfUpdateCount();
    }

    function bfLoad() {
        if (window._bf.loaded) return;
        window._bf.loaded = true;
        var grid = document.getElementById('brandCheckboxGrid');
        if (grid) grid.innerHTML = '<span style="color:var(--muted);font-size:0.75rem;">Caricamento...</span>';
        fetch('?get_brands=1')
            .then(function(r) { return r.json(); })
            .then(function(brands) {
                window._bf.brands = brands;
                brands.forEach(function(b) { window._bf.sel[b] = true; });
                bfRestore();
                bfRender();
                var hid = document.getElementById('marcheJson');
                if (hid && hid.value) {
                    document.getElementById('brandFilterBody').classList.add('open');
                    document.getElementById('brandChevron').classList.add('open');
                }
            })
            .catch(function() {
                window._bf.loaded = false;
                var g = document.getElementById('brandCheckboxGrid');
                if (g) g.innerHTML = '<span style="color:var(--red);font-size:0.75rem;">Errore caricamento</span>';
            });
    }

    window.toggleBrandFilter = function() {
        var body    = document.getElementById('brandFilterBody');
        var chevron = document.getElementById('brandChevron');
        if (!body) return;
        body.classList.toggle('open');
        chevron.classList.toggle('open');
        if (body.classList.contains('open')) bfLoad();
    };

    window.selectAllBrands = function() {
        window._bf.brands.forEach(function(b){ window._bf.sel[b] = true; });
        bfRender();
    };

    window.deselectAllBrands = function() {
        window._bf.brands.forEach(function(b){ window._bf.sel[b] = false; });
        bfRender();
    };

    (function(){
        try{
            var saved = JSON.parse(localStorage.getItem(BKEY));
            if (Array.isArray(saved) && saved.length > 0) {
                bfLoad();
                document.getElementById('brandFilterBody').classList.add('open');
                document.getElementById('brandChevron').classList.add('open');
            }
        } catch(e) {}
    })();

}); // end DOMContentLoaded

