// TUTORIAL — standalone, no DOMContentLoaded needed
(function() {
    var TKEY = 'fuelfinder_tutorial_done';
    var cur = 0;
    var dir = 1; // 1 = avanti, -1 = indietro
    function getSteps() {
        var T = window.FF_T || {};
        return [
            { icon: '📍', title: T.tut_1_title, desc: T.tut_1_desc },
            { icon: '🚗', title: T.tut_2_title, desc: T.tut_2_desc },
            { icon: '🔍', title: T.tut_3_title, desc: T.tut_3_desc },
            { icon: '⚙️', title: T.tut_4_title, desc: T.tut_4_desc },
            { icon: '🚨', title: T.tut_5_title, desc: T.tut_5_desc },
            { icon: '💰', title: T.tut_6_title, desc: T.tut_6_desc },
            { icon: '📲', title: T.tut_7_title, desc: T.tut_7_desc }
        ];
    }
    var steps = getSteps();

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

        var T = window.FF_T || {};
        btnNext.textContent = cur === steps.length - 1 ? (T.tutorial_start || 'Start') : (T.tutorial_next || 'Next');

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
        steps = getSteps();
        render(false);
    };

    // Auto-show on first visit — wait for DOM
    document.addEventListener('DOMContentLoaded', function() {
        steps = getSteps();
        try {
            if (!localStorage.getItem(TKEY)) render(false);
        } catch(e) { render(false); }
    });
})();
