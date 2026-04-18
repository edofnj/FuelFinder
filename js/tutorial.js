// TUTORIAL — standalone, no DOMContentLoaded needed
(function() {
    var TKEY = 'fuelfinder_tutorial_done';
    var cur = 0;
    var dir = 1; // 1 = avanti, -1 = indietro
    var steps = [
        { icon: '📍', title: 'Dove sei?',             desc: 'FuelFinder trova automaticamente dove ti trovi. Quando il telefono o il computer chiede il permesso per usare la posizione, premi Consenti — senza questo non riesce a cercare i distributori vicino a te.' },
        { icon: '🚗', title: 'Il mio garage',         desc: 'Puoi salvare la tua auto con il tipo di carburante e quanto consuma. La prossima volta che apri FuelFinder non dovrai reinserire nulla — basta selezionare la tua auto e via!' },
        { icon: '🔍', title: 'Come fare una ricerca', desc: 'Scegli il tipo di carburante, quanti km vuoi cercare intorno a te, e quanti litri vuoi fare. FuelFinder calcola quanto spendi davvero, contando anche i soldi per andarci e tornare.' },
        { icon: '⚙️', title: 'Filtra per marca',      desc: 'Hai una carta fedeltà Eni o preferisci sempre Q8? Apri il pannello Filtra marche e seleziona solo i distributori che vuoi vedere. La scelta viene ricordata anche la prossima volta.' },
        { icon: '🚨', title: 'Sto finendo!',          desc: 'Stai finendo la benzina? Premi il tasto rosso SOS e trovi subito il distributore più vicino a te in pochi secondi, senza altri calcoli.' },
        { icon: '💰', title: 'Il conto completo',     desc: 'Per ogni distributore vedi tre numeri: quanto spendi per il carburante, quanto costa il viaggio per andarci e tornare, e il totale finale. Così sai davvero quale conviene.' },
        { icon: '📲', title: 'Salvala sul telefono',  desc: 'Puoi usare FuelFinder come se fosse un\'app! Su iPhone apri questa pagina con Safari, tocca il tasto Condividi in basso e scegli Aggiungi a schermata Home. Su Android tocca i tre puntini in alto e scegli Aggiungi a schermata Home.' }
    ];

    function render(animated) {
        var overlay = document.getElementById('tutorialOverlay');
        var stepsEl = document.getElementById('tutorialSteps');
        var dotsEl  = document.getElementById('tutorialDots');
        var btnNext = document.getElementById('tutorialBtnNext');
        var btnBack = document.getElementById('tutorialBtnBack');
        if (!overlay || !stepsEl || !dotsEl || !btnNext) return;

        var s = steps[cur];
        var newContent =
            '<div class="tutorial-step">' +
                '<div class="tutorial-step-num">' + (cur + 1) + '</div>' +
                '<div class="tutorial-step-body">' +
                    '<div class="tutorial-step-title">' + s.icon + ' ' + s.title + '</div>' +
                    '<div class="tutorial-step-desc">' + s.desc + '</div>' +
                '</div>' +
            '</div>';

        if (animated) {
            // slide out vecchio contenuto
            var slideOutClass = dir > 0 ? 'slide-out-left' : 'slide-out-right';
            var slideInClass  = dir > 0 ? 'slide-in-right' : 'slide-in-left';
            stepsEl.classList.add(slideOutClass);
            setTimeout(function() {
                stepsEl.innerHTML = newContent;
                stepsEl.classList.remove(slideOutClass);
                stepsEl.classList.add(slideInClass);
                // forza reflow per far partire l'animazione
                void stepsEl.offsetWidth;
                stepsEl.classList.add('slide-in-active');
                setTimeout(function() {
                    stepsEl.classList.remove(slideInClass, 'slide-in-active');
                }, 350);
            }, 200);
        } else {
            stepsEl.innerHTML = newContent;
        }

        dotsEl.innerHTML = '';
        steps.forEach(function(_, i) {
            var dot = document.createElement('div');
            dot.className = 'tutorial-dot' + (i === cur ? ' active' : '');
            dot.onclick = function() {
                dir = i > cur ? 1 : -1;
                cur = i;
                render(true);
            };
            dotsEl.appendChild(dot);
        });

        btnNext.textContent = cur === steps.length - 1 ? 'Inizia →' : 'Avanti →';

        if (btnBack) {
            btnBack.style.display = cur === 0 ? 'none' : 'inline-flex';
        }

        overlay.style.display = 'flex';
        // blocca scroll body
        document.body.style.overflow = 'hidden';
    }

    window.tutorialNext = function() {
        dir = 1;
        if (cur < steps.length - 1) { cur++; render(true); }
        else { window.tutorialClose(); }
    };

    window.tutorialBack = function() {
        if (cur > 0) { dir = -1; cur--; render(true); }
    };

    window.tutorialClose = function() {
        var overlay = document.getElementById('tutorialOverlay');
        if (overlay) overlay.style.display = 'none';
        document.body.style.overflow = '';
        try { localStorage.setItem(TKEY, '1'); } catch(e) {}
    };

    window.tutorialOpen = function() {
        cur = 0;
        dir = 1;
        render(false);
    };

    // Auto-show on first visit — wait for DOM
    document.addEventListener('DOMContentLoaded', function() {
        try {
            if (!localStorage.getItem(TKEY)) render(false);
        } catch(e) { render(false); }
    });
})();
