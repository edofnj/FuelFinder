<?php
// Modal login/registrazione + JS account (incluso da index.php e route.php).
// Self-contained: definisce window.ffOpenAuth e gestisce menu/logout.
$ffDe = (function_exists('currentLang') && currentLang() === 'de');
?>
<div class="ff-modal" id="ffAuthModal" hidden>
    <div class="ff-modal-backdrop" onclick="ffCloseAuth()"></div>
    <div class="ff-modal-card" role="dialog" aria-modal="true">
        <button class="ff-modal-x" type="button" onclick="ffCloseAuth()" aria-label="Close">&times;</button>
        <div class="ff-modal-logo">Fuel<b>Finder</b></div>
        <div class="ff-tabs">
            <button class="ff-tab active" type="button" data-tab="login"><?= $ffDe ? 'Anmelden' : 'Accedi' ?></button>
            <button class="ff-tab" type="button" data-tab="register"><?= $ffDe ? 'Registrieren' : 'Registrati' ?></button>
        </div>
        <form class="ff-aform" id="ffLogin">
            <input type="email" name="email" placeholder="Email" autocomplete="email" required>
            <input type="password" name="password" placeholder="Password" autocomplete="current-password" required>
            <label class="ff-check"><input type="checkbox" name="remember" value="1"> <?= $ffDe ? 'Angemeldet bleiben' : 'Ricordami' ?></label>
            <button type="submit"><?= $ffDe ? 'Anmelden' : 'Accedi' ?></button>
            <div class="ff-amsg" id="ffLoginMsg"></div>
            <a href="#" id="ffForgot" style="display:block;text-align:center;margin-top:10px;font-size:.78rem;color:var(--muted,#94a3b8)"><?= $ffDe ? 'Passwort vergessen?' : 'Password dimenticata?' ?></a>
        </form>
        <form class="ff-aform" id="ffReset" hidden>
            <input type="email" name="email" placeholder="Email" autocomplete="email" required>
            <button type="submit"><?= $ffDe ? 'Reset-Link senden' : 'Invia link di reset' ?></button>
            <div class="ff-amsg" id="ffResetMsg"></div>
            <a href="#" id="ffResetBack" style="display:block;text-align:center;margin-top:8px;font-size:.78rem;color:var(--muted,#94a3b8)"><?= $ffDe ? 'Zurück zur Anmeldung' : 'Torna al login' ?></a>
        </form>
        <form class="ff-aform" id="ffRegister" hidden>
            <input type="email" name="email" placeholder="Email" autocomplete="email" required>
            <input type="password" name="password" placeholder="Password (min 8)" minlength="8" autocomplete="new-password" required>
            <button type="submit"><?= $ffDe ? 'Konto erstellen' : 'Crea account' ?></button>
            <div class="ff-amsg" id="ffRegisterMsg"></div>
        </form>
        <p class="ff-anote"><?= $ffDe ? 'Konto nötig, um Fahrzeuge in der Garage zu speichern.' : 'Serve un account per salvare i veicoli nel garage.' ?></p>
    </div>
</div>
<script>
(function(){
    var M=document.getElementById('ffAuthModal');
    var MSG={
        csrf:<?= json_encode($ffDe?'Sitzung abgelaufen, Seite neu laden.':'Sessione scaduta, ricarica la pagina.') ?>,
        invalid:<?= json_encode($ffDe?'E-Mail oder Passwort ungültig.':'Email o password non validi.') ?>,
        rate_limited:<?= json_encode($ffDe?'Zu viele Versuche, später erneut.':'Troppi tentativi, riprova tra qualche minuto.') ?>,
        email_invalid:<?= json_encode($ffDe?'E-Mail ungültig.':'Email non valida.') ?>,
        password_short:<?= json_encode($ffDe?'Passwort zu kurz (min. 8).':'Password troppo corta (min 8).') ?>,
        email_taken:<?= json_encode($ffDe?'E-Mail bereits registriert.':'Email già registrata.') ?>,
        db_error:<?= json_encode($ffDe?'Temporärer Fehler, erneut versuchen.':'Errore temporaneo, riprova.') ?>,
        unverified:<?= json_encode($ffDe?'E-Mail noch nicht bestätigt. Bitte prüfe deinen Posteingang.':'Email non ancora verificata. Controlla la posta (anche spam).') ?>,
        resend:<?= json_encode($ffDe?'Erneut senden':'Reinvia') ?>,
        resent:<?= json_encode($ffDe?'✓ Bestätigungs-E-Mail gesendet.':'✓ Email di verifica inviata.') ?>,
        verify_sent:<?= json_encode($ffDe?'✓ Fast fertig! Wir haben dir eine Bestätigungs-E-Mail geschickt. Bestätige sie, um dich anzumelden.':'✓ Quasi fatto! Ti abbiamo inviato un\'email di verifica. Confermala per poter accedere.') ?>
    };
    window.ffOpenAuth=function(tab){ if(!M) return; M.hidden=false; ffTab(tab||'login'); };
    window.ffCloseAuth=function(){ if(M) M.hidden=true; };
    function ffTab(t){
        document.querySelectorAll('.ff-tab').forEach(function(x){x.classList.toggle('active',x.dataset.tab===t);});
        document.getElementById('ffLogin').hidden = (t!=='login');
        document.getElementById('ffRegister').hidden = (t!=='register');
        var rf=document.getElementById('ffReset'); if(rf) rf.hidden = true;
    }
    document.querySelectorAll('.ff-tab').forEach(function(b){b.addEventListener('click',function(){ffTab(b.dataset.tab);});});
    function wire(form,action,msgId){
        if(!form) return;
        form.addEventListener('submit',function(e){
            e.preventDefault();
            var el=document.getElementById(msgId); el.textContent='';
            var fd=new FormData(form); fd.append('action',action); fd.append('csrf',window.FF_CSRF||'');
            fetch('/account',{method:'POST',body:fd,credentials:'same-origin'})
                .then(function(r){return r.json();})
                .then(function(d){ if(d.ok){ location.reload(); } else { el.textContent=MSG[d.error]||MSG.db_error; } })
                .catch(function(){ el.textContent=MSG.db_error; });
        });
    }
    // LOGIN (gestisce il caso 'unverified' con reinvio)
    (function(){
        var f=document.getElementById('ffLogin'); if(!f) return;
        f.addEventListener('submit',function(e){
            e.preventDefault();
            var el=document.getElementById('ffLoginMsg'); el.style.color=''; el.innerHTML='';
            var fd=new FormData(f); fd.append('action','login'); fd.append('csrf',window.FF_CSRF||'');
            fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(d.ok){ location.reload(); return; }
                if(d.error==='unverified'){
                    el.innerHTML = MSG.unverified + ' <a href="#" id="ffResend2" style="color:#10b981">' + MSG.resend + '</a>';
                    var rs=document.getElementById('ffResend2');
                    if(rs) rs.addEventListener('click',function(ev){ ev.preventDefault();
                        var fd2=new FormData(); fd2.append('action','resend_verify'); fd2.append('csrf',window.FF_CSRF||'');
                        fd2.append('email', f.email.value); fd2.append('password', f.password.value);
                        fetch('/account',{method:'POST',body:fd2,credentials:'same-origin'}).then(function(){ el.style.color='#10b981'; el.textContent=MSG.resent; });
                    });
                } else { el.textContent = MSG[d.error] || MSG.db_error; }
            }).catch(function(){ el.textContent=MSG.db_error; });
        });
    })();
    // REGISTER (niente auto-login: mostra "verifica la tua email")
    (function(){
        var f=document.getElementById('ffRegister'); if(!f) return;
        f.addEventListener('submit',function(e){
            e.preventDefault();
            var el=document.getElementById('ffRegisterMsg'); el.style.color=''; el.textContent='';
            var fd=new FormData(f); fd.append('action','register'); fd.append('csrf',window.FF_CSRF||'');
            fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){
                if(d.ok){ el.style.color='#10b981'; el.textContent=MSG.verify_sent; f.reset(); }
                else { el.textContent = MSG[d.error] || MSG.db_error; }
            }).catch(function(){ el.textContent=MSG.db_error; });
        });
    })();
    // password dimenticata
    var fForgot=document.getElementById('ffForgot'), fReset=document.getElementById('ffReset'), fLogin=document.getElementById('ffLogin');
    if(fForgot){ fForgot.addEventListener('click',function(e){e.preventDefault(); fLogin.hidden=true; fReset.hidden=false; }); }
    var fBack=document.getElementById('ffResetBack');
    if(fBack){ fBack.addEventListener('click',function(e){e.preventDefault(); fReset.hidden=true; fLogin.hidden=false; }); }
    if(fReset){ fReset.addEventListener('submit',function(e){
        e.preventDefault();
        var el=document.getElementById('ffResetMsg'); el.style.color=''; el.textContent='';
        var fd=new FormData(fReset); fd.append('action','request_reset'); fd.append('csrf',window.FF_CSRF||'');
        fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(){
            el.style.color='#10b981'; el.textContent=<?= json_encode($ffDe ? 'Falls die E-Mail existiert, wurde ein Link gesendet.' : 'Se l\'email esiste, ti abbiamo inviato un link.') ?>;
        }).catch(function(){ el.textContent=MSG.db_error; });
    }); }
    // menu account
    var ab=document.getElementById('acctBtn'), am=document.getElementById('acctMenu');
    if(ab&&am){
        ab.addEventListener('click',function(e){e.stopPropagation();am.hidden=!am.hidden;});
        document.addEventListener('click',function(){am.hidden=true;});
    }
    var lo=document.getElementById('acctLogout');
    if(lo){ lo.addEventListener('click',function(e){e.preventDefault();var fd=new FormData();fd.append('action','logout');fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(){location.reload();});}); }
    var rv=document.getElementById('acctResendVerify');
    if(rv){ rv.addEventListener('click',function(e){e.preventDefault();var fd=new FormData();fd.append('action','resend_verify');fd.append('csrf',window.FF_CSRF||'');fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(){ rv.textContent=<?= json_encode($ffDe ? '✓ E-Mail gesendet' : '✓ Email inviata') ?>; }); }); }
    var da=document.getElementById('acctDelete');
    if(da){ da.addEventListener('click',function(e){e.preventDefault(); if(!confirm(<?= json_encode($ffDe ? 'Konto und alle Daten endgültig löschen?' : 'Eliminare definitivamente account e tutti i dati?') ?>)) return; var fd=new FormData();fd.append('action','delete_account');fd.append('csrf',window.FF_CSRF||'');fetch('/account',{method:'POST',body:fd,credentials:'same-origin'}).then(function(){location.href='/';}); }); }
})();
</script>
