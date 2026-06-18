<?php
// Banner informativo cookie + footer con link privacy e attribuzione fonti dati.
// Uso solo cookie tecnici/funzionali + analytics anonime → banner informativo.
$bDe = (function_exists('currentLang') && currentLang() === 'de');
?>
<div class="ff-foot" id="ffFoot">
    <a href="/privacy"><?= $bDe ? 'Datenschutz' : 'Privacy' ?></a>
    <a href="/tos"><?= $bDe ? 'AGB' : 'Termini' ?></a>
    <span class="ff-credits"><?= $bDe ? 'Daten' : 'Dati' ?>: MIMIT &middot; <a href="https://creativecommons.tankerkoenig.de" target="_blank" rel="noopener">Tankerkönig</a> &middot; &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a></span>
</div>
<div class="ff-cookie" id="ffCookie" hidden>
    <span><?= $bDe
        ? 'Wir verwenden nur technisch notwendige Cookies und anonyme Statistiken (keine Profilbildung).'
        : 'Usiamo solo cookie tecnici necessari e statistiche anonime, senza profilazione.' ?>
        <a href="/privacy"><?= $bDe ? 'Mehr erfahren' : 'Maggiori info' ?></a>
    </span>
    <button type="button" id="ffCookieOk"><?= $bDe ? 'Verstanden' : 'Ho capito' ?></button>
</div>
<style>
.ff-foot{position:fixed;left:14px;bottom:14px;z-index:990;display:flex;gap:10px;align-items:center;font-size:.72rem;background:rgba(13,13,26,.6);padding:5px 10px;border-radius:8px;backdrop-filter:blur(6px)}
.ff-foot a{color:var(--muted,#94a3b8);text-decoration:none}
.ff-foot a:hover{color:var(--accent,#10b981)}
.ff-credits{color:var(--muted,#94a3b8)}
.ff-cookie{position:fixed;left:14px;right:14px;bottom:14px;z-index:996;max-width:680px;margin:0 auto;display:flex;gap:14px;align-items:center;justify-content:space-between;background:#131c2e;border:1px solid var(--glass-border,#2c3a50);border-radius:14px;padding:12px 16px;box-shadow:0 12px 40px rgba(0,0,0,.5);font-size:.82rem;color:#d4d4e4}
.ff-cookie[hidden]{display:none}
.ff-cookie a{color:var(--accent,#10b981)}
.ff-cookie button{flex:none;background:var(--accent,#10b981);color:#04211a;border:none;border-radius:9px;padding:9px 16px;font-weight:700;cursor:pointer;font-family:inherit;font-size:.82rem}
@media(max-width:560px){.ff-cookie{flex-direction:column;align-items:stretch;text-align:center}.ff-credits{display:none}}
</style>
<script>
(function(){
    var ACK='ff_privacy_ack';
    var c=document.getElementById('ffCookie'), foot=document.getElementById('ffFoot');
    var seen=false; try{ seen=!!localStorage.getItem(ACK); }catch(e){}
    if(!seen){ c.hidden=false; if(foot) foot.style.display='none'; }
    var b=document.getElementById('ffCookieOk');
    if(b) b.addEventListener('click',function(){ try{localStorage.setItem(ACK,'1');}catch(e){} c.hidden=true; if(foot) foot.style.display=''; });
})();
</script>
