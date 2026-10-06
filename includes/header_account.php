<?php
// Widget account nell'header (incluso da index.php e route.php) + menu condiviso degli strumenti.
// Profilo, email, password e sicurezza si gestiscono sull'account unico (ACCOUNT_PAGE_URL);
// /profile mostra solo i dati di FuelFinder.
$ffUser = function_exists('currentUser') ? currentUser() : null;
$ffDe   = (function_exists('currentLang') && currentLang() === 'de');
?>
<script src="https://www.fmenegazzi.it/fm-switcher.js" defer></script>
<fm-switcher current="fuelfinder" theme="dark" align="end" class="ff-switcher"></fm-switcher>
<div class="acct-wrap">
<?php if ($ffUser): ?>
    <button type="button" class="acct-btn" id="acctBtn" aria-haspopup="true" title="<?= htmlspecialchars($ffUser['email']) ?>"><?= htmlspecialchars(strtoupper(mb_substr($ffUser['email'], 0, 1))) ?></button>
    <div class="acct-menu" id="acctMenu" hidden>
        <div class="acct-email"><?= htmlspecialchars($ffUser['email']) ?></div>
        <a href="<?= htmlspecialchars(ACCOUNT_PAGE_URL) ?>" class="acct-link">&#9881; <?= $ffDe ? 'Mein Konto' : 'Il mio account' ?></a>
        <a href="/profile" class="acct-link">&#9981; <?= $ffDe ? 'Meine FuelFinder-Daten' : 'I miei dati FuelFinder' ?></a>
        <?php if (!empty($ffUser['is_admin'])): ?><a href="/stats" class="acct-link">&#128202; <?= $ffDe ? 'Statistiken' : 'Metriche' ?></a><?php endif; ?>
        <a href="/oidc?logout" class="acct-link"><?= $ffDe ? 'Abmelden' : 'Esci' ?></a>
    </div>
<?php else: ?>
    <button type="button" class="acct-btn acct-login" onclick="ffOpenAuth('login')"><?= $ffDe ? 'Anmelden' : 'Accedi' ?></button>
<?php endif; ?>
</div>
<script>
(function(){
  // Login/registrazione con l'account unico fmenegazzi; dopo si torna alla pagina corrente.
  window.ffOpenAuth=function(t){ var next=encodeURIComponent(location.pathname+location.search);
    location.href='/oidc?start'+(t==='register'?'&register=1':'')+'&next='+next; };
  var ab=document.getElementById('acctBtn'), am=document.getElementById('acctMenu');
  if(ab&&am){ ab.addEventListener('click',function(e){e.stopPropagation();am.hidden=!am.hidden;}); document.addEventListener('click',function(){am.hidden=true;}); }
})();
</script>
